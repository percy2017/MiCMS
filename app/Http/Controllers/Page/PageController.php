<?php

namespace App\Http\Controllers\Page;

use App\Http\Controllers\Controller;
use App\Http\Requests\Page\StorePageRequest;
use App\Http\Requests\Page\UpdatePageRequest;
use App\Models\Media;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Setting;
use App\Services\PackageManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Page::class);

        $showTrashed = $request->boolean('trashed');

        $pages = Page::query()
            ->with('uploader:id,name')
            ->when($showTrashed, fn ($q) => $q->onlyTrashed())
            ->when(! $showTrashed, fn ($q) => $q->whereNull('deleted_at'))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = (string) $request->input('search');
                $query->where(function ($q) use ($search): void {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), function ($query) use ($request): void {
                $query->where('status', (string) $request->input('status'));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Page $page): array => $this->present($page));

        return Inertia::render('paginas/index', [
            'pages' => $pages,
            'filters' => [
                'search' => (string) $request->input('search', ''),
                'status' => $request->input('status'),
                'trashed' => $showTrashed,
            ],
        ]);
    }

    public function store(StorePageRequest $request): RedirectResponse
    {
        $this->authorize('create', Page::class);

        $page = Page::create([
            'user_id' => $request->user()->id,
            'title' => $request->string('title')->toString(),
            'slug' => $request->string('slug')->toString(),
            'status' => Page::STATUS_DRAFT,
            'puck_data' => null,
        ]);

        return to_route('admin.paginas.edit', ['page' => $page->id])
            ->with('success', 'Página creada. Ahora puedes diseñarla con Puck.');
    }

    public function edit(Request $request, Page $page): Response
    {
        $this->authorize('update', $page);

        $locations = array_keys((array) config('menus.locations', []));
        $menus = Menu::query()
            ->with([
                'items' => fn ($q) => $q->whereNull('parent_id')->orderBy('order')->orderBy('id'),
                'items.children' => fn ($q) => $q->orderBy('order')->orderBy('id'),
                'items.children.children' => fn ($q) => $q->orderBy('order')->orderBy('id'),
                'items.page:id,title,slug,is_home,status',
                'items.children.page:id,title,slug,is_home,status',
                'items.children.children.page:id,title,slug,is_home,status',
            ])
            ->whereIn('location', $locations)
            ->get()
            ->groupBy('location')
            ->map(fn ($group, $location) => [
                'id' => $group->first()->id,
                'name' => $group->first()->name,
                'location' => $location,
                'items' => $group->first()->items
                    ->map(fn ($item) => $item->present())
                    ->values()
                    ->all(),
            ])
            ->all();

        return Inertia::render('paginas/editar', [
            'page' => $this->present($page, withContent: true),
            'menus' => $menus,
        ]);
    }

    public function update(UpdatePageRequest $request, Page $page): RedirectResponse
    {
        $this->authorize('update', $page);

        $data = $request->validated();

        $sanitizedPuck = $request->sanitizedPuckData();
        if ($sanitizedPuck !== null) {
            $page->puck_data = $sanitizedPuck;
        }

        if (isset($data['title'])) {
            $page->title = $data['title'];
        }

        if (isset($data['slug'])) {
            $page->slug = $data['slug'];
        }

        if (array_key_exists('status', $data)) {
            $page->status = $data['status'];
            if ($data['status'] === Page::STATUS_PUBLISHED && $page->published_at === null) {
                $page->published_at = now();
            }
            if ($data['status'] === Page::STATUS_DRAFT) {
                $page->published_at = null;
            }
        }

        $page->save();

        $this->flushPageCache($page);

        return back()->with('success', 'Página guardada.');
    }

    public function destroy(Request $request, Page $page): RedirectResponse
    {
        $this->authorize('delete', $page);

        $previousSlug = $page->slug;
        $wasHome = $page->is_home;
        $wasPublished = $page->isPublished();

        DB::transaction(function () use ($page): void {
            if ($page->is_home) {
                Page::query()->where('is_home', true)->update(['is_home' => false]);
            }
            $page->delete();
        });

        if ($wasPublished) {
            Cache::forget("page.show.{$previousSlug}");
        }
        if ($wasHome) {
            Cache::forget('page.home');
        }

        return to_route('admin.paginas.index')->with('success', 'Página eliminada.');
    }

    public function restore(Request $request, int $page): RedirectResponse
    {
        $pageModel = Page::onlyTrashed()->findOrFail($page);
        $this->authorize('delete', $pageModel);

        $pageModel->restore();

        return to_route('admin.paginas.index')->with('success', 'Página restaurada.');
    }

    public function forceDestroy(Request $request, int $page): RedirectResponse
    {
        $pageModel = Page::onlyTrashed()->findOrFail($page);
        $this->authorize('delete', $pageModel);

        $pageModel->forceDelete();

        return to_route('admin.paginas.index')->with('success', 'Página eliminada permanentemente.');
    }

    public function setHome(Request $request, Page $page): RedirectResponse
    {
        $this->authorize('setHome', $page);

        DB::transaction(function () use ($page): void {
            Page::query()->where('is_home', true)->where('id', '!=', $page->id)->update(['is_home' => false]);
            $page->is_home = true;
            $page->save();
        });

        Cache::forget('page.home');

        return back()->with('success', 'Página establecida como inicio.');
    }

    public function unsetHome(Request $request, Page $page): RedirectResponse
    {
        $this->authorize('setHome', $page);

        $page->is_home = false;
        $page->save();

        Cache::forget('page.home');

        return back()->with('success', 'Página de inicio removida.');
    }

    public function show(Request $request, string $slug): Response
    {
        $payload = Cache::remember(
            "page.show.{$slug}",
            now()->addMinutes(5),
            function () use ($slug) {
                $page = Page::query()
                    ->where('slug', $slug)
                    ->where('status', Page::STATUS_PUBLISHED)
                    ->whereNull('deleted_at')
                    ->first();

                if (! $page) {
                    return null;
                }

                return $this->presentPublic($page);
            }
        );

        if ($payload === null) {
            abort(404);
        }

        return Inertia::render('paginas/show', $payload);
    }

    public function home(Request $request): Response|RedirectResponse
    {
        $payload = Cache::remember(
            'page.home',
            now()->addMinutes(5),
            function () {
                $home = Page::query()
                    ->where('is_home', true)
                    ->where('status', Page::STATUS_PUBLISHED)
                    ->whereNull('deleted_at')
                    ->first();

                if ($home) {
                    return $this->presentPublic($home);
                }

                return null;
            }
        );

        if ($payload === null) {
            return Inertia::render('welcome', [
                'menus' => $this->menusForLayout(),
            ]);
        }

        return Inertia::render('paginas/show', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    protected function presentPublic(Page $page, ?PackageManager $packages = null): array
    {
        $packages ??= app(PackageManager::class);

        return [
            'page' => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'puck_data' => $page->puck_data ?? ['content' => [], 'root' => ['props' => []], 'zones' => []],
            ],
            'menus' => $this->menusForLayout(),
            'site' => $this->siteMeta(),
            'enabledPackages' => $packages->enabled()
                ->map(fn (array $p): array => [
                    'slug' => $p['slug'],
                    'label' => $p['menu_label'],
                    'icon' => $p['icon'],
                    'menu' => $p['menu'],
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function siteMeta(): array
    {
        $site = Setting::site();
        $logoId = $site['site_logo'] ?? null;

        return [
            'name' => $site['site_name'] ?? config('app.name'),
            'tagline' => $site['site_tagline'] ?? '',
            'logo_url' => $logoId ? Media::find($logoId)?->url() : null,
        ];
    }

    /**
     * @return array<string, array{id: int, name: string, location: string, items: array<int, array<string, mixed>>}>
     */
    protected function menusForLayout(): array
    {
        $locations = array_keys((array) config('menus.locations', []));

        if ($locations === []) {
            return [];
        }

        return Menu::query()
            ->with([
                'items' => fn ($q) => $q->whereNull('parent_id')->orderBy('order')->orderBy('id'),
                'items.children' => fn ($q) => $q->orderBy('order')->orderBy('id'),
                'items.children.children' => fn ($q) => $q->orderBy('order')->orderBy('id'),
                'items.page:id,title,slug,is_home,status',
                'items.children.page:id,title,slug,is_home,status',
                'items.children.children.page:id,title,slug,is_home,status',
            ])
            ->whereIn('location', $locations)
            ->get()
            ->groupBy('location')
            ->map(fn ($group, $location) => [
                'id' => $group->first()->id,
                'name' => $group->first()->name,
                'location' => $location,
                'items' => $group->first()->items
                    ->map(fn ($item) => $item->present())
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    /**
     * Invalidate cache for a page after a write.
     * Always flush by-slug; also flush by-id (rename support) and home.
     */
    protected function flushPageCache(Page $page): void
    {
        if (isset($page->getOriginal()['slug'])) {
            $oldSlug = $page->getOriginal()['slug'];
            Cache::forget("page.show.{$oldSlug}");
        }

        Cache::forget("page.show.{$page->slug}");
        Cache::forget("page.show.{$page->id}");
        Cache::forget('page.home');
    }

    /**
     * @return array<string, mixed>
     */
    protected function present(Page $page, bool $withContent = false): array
    {
        $data = [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'status' => $page->status,
            'is_published' => $page->isPublished(),
            'is_draft' => $page->isDraft(),
            'is_home' => (bool) $page->is_home,
            'published_at' => $page->published_at?->toIso8601String(),
            'created_at' => $page->created_at?->toIso8601String(),
            'updated_at' => $page->updated_at?->toIso8601String(),
            'created_at_diff' => $page->created_at?->diffForHumans(),
            'updated_at_diff' => $page->updated_at?->diffForHumans(),
            'uploader' => $page->uploader ? [
                'id' => $page->uploader->id,
                'name' => $page->uploader->name,
            ] : null,
            'public_url' => $page->publicUrl(),
        ];

        if ($withContent) {
            $data['puck_data'] = $page->puck_data ?? ['content' => [], 'root' => ['props' => []], 'zones' => []];
        }

        return $data;
    }
}

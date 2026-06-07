<?php

namespace App\Http\Controllers\Menu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Menu\StoreMenuRequest;
use App\Http\Requests\Menu\UpdateMenuRequest;
use App\Models\Menu;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Menu::class);

        $menus = Menu::query()
            ->withCount('items')
            ->latest()
            ->get()
            ->map(fn (Menu $menu): array => $menu->present());

        return Inertia::render('menus/index', [
            'menus' => $menus,
            'locations' => (array) config('menus.locations', []),
        ]);
    }

    public function store(StoreMenuRequest $request): RedirectResponse
    {
        $this->authorize('create', Menu::class);

        $menu = Menu::create($request->validated());

        return to_route('admin.menus.edit', ['menu' => $menu->id])
            ->with('success', 'Menú creado. Añade los elementos del menú.');
    }

    public function edit(Request $request, Menu $menu): Response
    {
        $this->authorize('update', $menu);

        $menu->load([
            'items' => fn ($q) => $q->whereNull('parent_id')->orderBy('order')->orderBy('id'),
            'items.children' => fn ($q) => $q->orderBy('order')->orderBy('id'),
            'items.children.children' => fn ($q) => $q->orderBy('order')->orderBy('id'),
            'items.page:id,title,slug,is_home,status',
            'items.children.page:id,title,slug,is_home,status',
            'items.children.children.page:id,title,slug,is_home,status',
        ]);

        $items = $menu->items->map(fn ($item) => $item->present())->values();

        $assignedLocations = Menu::query()
            ->where('id', '!=', $menu->id)
            ->pluck('location')
            ->all();

        $availableLocations = collect((array) config('menus.locations', []))
            ->map(fn (string $label, string $key): array => [
                'value' => $key,
                'label' => $label,
            ])
            ->values();

        $pageIds = $menu->items->pluck('page_id')->filter()->all();

        $pages = Page::query()
            ->where(function ($q) {
                $q->where('status', Page::STATUS_PUBLISHED);
            })
            ->orWhereIn('id', $pageIds)
            ->orderBy('title')
            ->get(['id', 'title', 'slug', 'is_home', 'status'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'slug' => $p->slug,
                'is_home' => (bool) $p->is_home,
                'status' => $p->status,
            ])
            ->values();

        return Inertia::render('menus/editar', [
            'menu' => [
                'id' => $menu->id,
                'name' => $menu->name,
                'location' => $menu->location,
                'location_label' => $menu->locationLabel(),
                'items' => $items,
            ],
            'locations' => $availableLocations,
            'pages' => $pages,
        ]);
    }

    public function update(UpdateMenuRequest $request, Menu $menu): RedirectResponse
    {
        $this->authorize('update', $menu);

        $menu->update($request->validated());

        return back()->with('success', 'Menú actualizado.');
    }

    public function destroy(Request $request, Menu $menu): RedirectResponse
    {
        $this->authorize('delete', $menu);

        $menu->delete();

        return to_route('admin.menus.index')->with('success', 'Menú eliminado.');
    }
}

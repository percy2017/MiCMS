<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Http\Requests\Media\MediaUpdateRequest;
use App\Models\Media;
use App\Support\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MediaController extends Controller
{
    public function __construct(private readonly MediaStorage $storage) {}

    /**
     * Display a paginated, searchable list of media items.
     */
    public function index(Request $request): Response|JsonResponse
    {
        $this->authorize('viewAny', Media::class);

        $query = Media::query()->latest('created_at');

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('alt_text', 'like', "%{$search}%");
            });
        }

        if ($type = $request->query('type')) {
            $query->where('mime_type', 'like', $type.'/%');
        }

        $perPage = (int) $request->query('per_page', 24);
        $perPage = max(1, min($perPage, 100));

        $media = $query->paginate($perPage)->withQueryString()->through(fn (Media $m) => [
            'id' => $m->id,
            'name' => $m->name,
            'title' => $m->title,
            'mime_type' => $m->mime_type,
            'size' => $m->size,
            'human_size' => $m->humanSize(),
            'width' => $m->width,
            'height' => $m->height,
            'url' => $m->url(),
            'is_image' => $m->isImage(),
            'is_video' => $m->isVideo(),
            'is_audio' => $m->isAudio(),
            'created_at' => $m->created_at->toISOString(),
            'created_at_diff' => $m->created_at->diffForHumans(),
        ]);

        if ($request->wantsJson() || $request->header('X-Inertia-Partial-Data')) {
            return response()->json([
                'media' => $media,
                'filters' => [
                    'search' => $request->query('search', ''),
                    'type' => $request->query('type'),
                ],
                'max_size' => MediaStorage::effectiveMaxSize(),
            ]);
        }

        return Inertia::render('media/index', [
            'media' => $media,
            'filters' => [
                'search' => $request->query('search', ''),
                'type' => $request->query('type'),
            ],
            'max_size' => MediaStorage::effectiveMaxSize(),
        ]);
    }

    /**
     * Display a single media item.
     */
    public function show(Media $media): Response
    {
        $this->authorize('view', $media);

        return Inertia::render('media/show', [
            'media' => $this->present($media),
        ]);
    }

    /**
     * Show the form for editing a media item's metadata.
     */
    public function edit(Media $media): Response
    {
        $this->authorize('update', $media);

        return Inertia::render('media/edit', [
            'media' => $this->present($media),
        ]);
    }

    /**
     * Update the media item's metadata.
     */
    public function update(MediaUpdateRequest $request, Media $media): RedirectResponse
    {
        $this->authorize('update', $media);

        $media->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Media updated.')]);

        return back();
    }

    /**
     * Remove the media item and its underlying file.
     */
    public function destroy(Media $media): RedirectResponse
    {
        $this->authorize('delete', $media);

        $this->storage->delete($media);
        $media->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Media deleted.')]);

        return to_route('admin.media.index');
    }

    /**
     * Shape a media model for the frontend.
     *
     * @return array<string, mixed>
     */
    protected function present(Media $media): array
    {
        return [
            'id' => $media->id,
            'name' => $media->name,
            'title' => $media->title,
            'alt_text' => $media->alt_text,
            'caption' => $media->caption,
            'description' => $media->description,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'human_size' => $media->humanSize(),
            'width' => $media->width,
            'height' => $media->height,
            'url' => $media->url(),
            'is_image' => $media->isImage(),
            'is_video' => $media->isVideo(),
            'is_audio' => $media->isAudio(),
            'created_at' => $media->created_at->toISOString(),
            'created_at_diff' => $media->created_at->diffForHumans(),
        ];
    }
}

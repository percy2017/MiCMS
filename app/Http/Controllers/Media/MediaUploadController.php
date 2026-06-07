<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Http\Requests\Media\MediaUploadRequest;
use App\Models\Media;
use App\Support\MediaStorage;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class MediaUploadController extends Controller
{
    public function __construct(private readonly MediaStorage $storage) {}

    /**
     * Store a newly uploaded media file.
     */
    public function store(MediaUploadRequest $request): RedirectResponse
    {
        $this->authorize('create', Media::class);

        $file = $request->file('file');
        $stored = $this->storage->store($file);

        $media = $request->user()->media()->create([
            'disk' => config('media.disk'),
            ...$stored,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Media uploaded.')]);

        return to_route('admin.media.edit', $media);
    }
}

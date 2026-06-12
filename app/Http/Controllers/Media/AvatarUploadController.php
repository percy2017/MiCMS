<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Http\Requests\Media\MediaUploadRequest;
use App\Models\Media;
use App\Support\MediaStorage;
use Illuminate\Http\JsonResponse;

class AvatarUploadController extends Controller
{
    public function __construct(private readonly MediaStorage $storage) {}

    /**
     * Upload a file and return JSON with the created Media.
     * Used by avatar pickers in user forms.
     */
    public function store(MediaUploadRequest $request): JsonResponse
    {
        $this->authorize('create', Media::class);

        $file = $request->file('file');
        $stored = $this->storage->store($file);

        $media = $request->user()->media()->create([
            'disk' => config('media.disk'),
            ...$stored,
        ]);

        return response()->json([
            'id' => $media->id,
            'name' => $media->name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'url' => $media->url(),
        ]);
    }
}

<?php

namespace App\Support;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MediaStorage
{
    /**
     * The maximum upload size that PHP will actually accept, capped at
     * `media.max_size`. Lets us keep server-side validation in sync with
     * what PHP lets through, regardless of `upload_max_filesize`.
     */
    public static function effectiveMaxSize(): int
    {
        return min(
            (int) config('media.max_size'),
            Bytes::fromIni((string) ini_get('upload_max_filesize')) ?: PHP_INT_MAX,
            Bytes::fromIni((string) ini_get('post_max_size')) ?: PHP_INT_MAX,
        );
    }

    /**
     * Store a file on the configured media disk under the configured directory
     * using the file's original name. If a file with the same name already
     * exists, append a numeric suffix (-1, -2, ...).
     *
     * @return array{path: string, name: string, mime_type: string, size: int, width: ?int, height: ?int}
     */
    public function store(UploadedFile $file): array
    {
        $disk = config('media.disk');
        $directory = trim(config('media.directory'), '/');
        $originalName = $file->getClientOriginalName();
        $basename = pathinfo($originalName, PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();

        $uniqueName = $this->uniqueFilename($disk, $directory, $basename, $extension);
        $relativePath = $directory === '' ? $uniqueName : $directory.'/'.$uniqueName;

        Storage::disk($disk)->putFileAs(
            $directory === '' ? '' : $directory,
            $file,
            $uniqueName,
        );

        [$width, $height] = $this->imageDimensions($file, $file->getMimeType() ?: 'application/octet-stream');

        return [
            'path' => $relativePath,
            'name' => $originalName,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => (int) $file->getSize(),
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * Remove a media file from storage if it exists.
     */
    public function delete(Media $media): void
    {
        Storage::disk($media->disk)->delete($media->path);
    }

    /**
     * Compute a unique filename inside the directory. If "photo.jpg" exists,
     * returns "photo-1.jpg", then "photo-2.jpg", and so on.
     */
    protected function uniqueFilename(string $disk, string $directory, string $basename, string $extension): string
    {
        $extension = $extension === '' ? '' : '.'.$extension;
        $candidate = $basename.$extension;
        $suffix = 1;

        while (Storage::disk($disk)->exists($directory === '' ? $candidate : $directory.'/'.$candidate)) {
            $candidate = $basename.'-'.$suffix.$extension;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * Read width and height for image uploads. Returns [null, null] for
     * non-image files.
     *
     * @return array{0: ?int, 1: ?int}
     */
    protected function imageDimensions(UploadedFile $file, string $mimeType): array
    {
        if (! str_starts_with($mimeType, 'image/')) {
            return [null, null];
        }

        $path = $file->getRealPath();

        if ($path === false) {
            return [null, null];
        }

        $info = @getimagesize($path);

        if ($info === false) {
            return [null, null];
        }

        return [(int) $info[0], (int) $info[1]];
    }
}

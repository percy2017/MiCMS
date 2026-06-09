<?php

namespace App\LogViewer;

use Opcodes\LogViewer\LogViewerService as BaseLogViewerService;

class LogViewerService extends BaseLogViewerService
{
    public function basePathForLogs(): string
    {
        $path = config('log-viewer.path') ?? storage_path('logs');

        if (! is_string($path) || $path === '') {
            $path = storage_path('logs');
        }

        $real = realpath($path);

        if ($real === false) {
            @mkdir($path, 0755, true);
            $real = realpath($path);
        }

        return rtrim($real ?? $path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
    }
}

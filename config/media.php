<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Storage Disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk where uploaded media files will be stored. Defaults
    | to the "public" disk so files are served from /storage/media/... via
    | the storage:link symlink. Switch to "s3" (or any other disk defined in
    | config/filesystems.php) by changing MEDIA_DISK in your .env file.
    |
    */

    'disk' => env('MEDIA_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Storage Directory
    |--------------------------------------------------------------------------
    |
    | The directory inside the disk where media files are stored. Files keep
    | their original name on disk; duplicates are renamed with a numeric
    | suffix (e.g. "photo.jpg", "photo-1.jpg", "photo-2.jpg").
    |
    */

    'directory' => '',

    /*
    |--------------------------------------------------------------------------
    | Maximum File Size
    |--------------------------------------------------------------------------
    |
    | The maximum file size accepted during upload, in bytes. Defaults to
    | 50 MB. Configure per environment via MEDIA_MAX_SIZE in your .env file.
    |
    */

    'max_size' => (int) env('MEDIA_MAX_SIZE', 50 * 1024 * 1024),

    /*
    |--------------------------------------------------------------------------
    | Blocked Extensions
    |--------------------------------------------------------------------------
    |
    | WARNING: the "public" disk is served directly by the web server, so any
    | file stored there with an executable extension can be run by the server
    | (e.g. .php, .phtml, .phar, .exe, .sh, .bat). The default list below is
    | a minimum safeguard. If you remove it, ensure your web server is
    | configured to never execute scripts from the storage directory.
    |
    | The "htaccess" entry is included to prevent uploads of .htaccess files
    | that could override the web server configuration.
    |
    */

    'blocked_extensions' => [
        'php',
        'phtml',
        'phar',
        'exe',
        'sh',
        'bat',
        'htaccess',
    ],
];

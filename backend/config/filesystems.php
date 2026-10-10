<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Image storage
    |--------------------------------------------------------------------------
    |
    | Read only through App\Support\MediaStorage. Two disks, because an R2
    | bucket is public or private as a whole:
    |
    |  - media_disk: admin media-library uploads, served at a public URL.
    |  - private_media_disk: customers' DIY reference photos, shown only
    |    through short-lived signed links.
    |
    | Both default to `public` (this machine's storage/app/public) for local
    | development and tests. Production sets them to `r2` and `r2-private`.
    |
    */

    'media_disk' => env('MEDIA_DISK', 'public'),

    'private_media_disk' => env('PRIVATE_MEDIA_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Cloudflare R2. It speaks the S3 protocol, hence the `s3` driver;
        // no AWS account is involved. R2_ENDPOINT is
        // https://<account id>.r2.cloudflarestorage.com. `throw` so a failed
        // upload is an error, not a `false` saved as an image path.
        'r2' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => 'auto',
            'bucket' => env('R2_BUCKET'),
            // The bucket's public address: a custom domain such as
            // https://media.goldcoasttokota.store.
            'url' => env('R2_PUBLIC_URL'),
            'endpoint' => env('R2_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'throw' => true,
            'report' => false,
        ],

        // No public access and no `url`: files here are reachable only
        // through MediaStorage::privateUrl()'s signed links.
        'r2-private' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => 'auto',
            'bucket' => env('R2_PRIVATE_BUCKET'),
            'endpoint' => env('R2_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'throw' => true,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];

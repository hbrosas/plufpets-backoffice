<?php

$mediaBucket = env('AWS_BUCKET', 'media');
$supabaseUrl = rtrim((string) env('SUPABASE_URL'), '/');

$supabaseDisk = [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION', 'ap-northeast-1'),
    'bucket' => $mediaBucket,
    'url' => env('AWS_URL', $supabaseUrl === '' ? null : $supabaseUrl.'/storage/v1/object/public/'.$mediaBucket),
    'endpoint' => env('AWS_ENDPOINT', $supabaseUrl === '' ? null : $supabaseUrl.'/storage/v1/s3'),
    'use_path_style_endpoint' => filter_var(env('AWS_USE_PATH_STYLE_ENDPOINT', true), FILTER_VALIDATE_BOOLEAN),
    'visibility' => 'private',
    'throw' => false,
    'report' => false,
    'options' => [
        'params' => [
            'ACL' => '',
        ],
    ],
];

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the disks below should be used as the
    | default disk for filesystem operations. The public disk stores
    | catalog photos and brand logos.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

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
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        'public' => env('FILESYSTEM_PUBLIC_DRIVER', 'local') === 's3' ? $supabaseDisk : [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => '/storage',
            'visibility' => 'public',
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        's3' => $supabaseDisk,

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

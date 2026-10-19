<?php

if (getenv('VERCEL')) {
    foreach ([
        '/tmp/views',
        '/tmp/storage/app/public',
        '/tmp/storage/framework/cache/data',
        '/tmp/storage/framework/sessions',
        '/tmp/storage/framework/views',
        '/tmp/storage/logs',
    ] as $directory) {
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
}

require __DIR__.'/../public/index.php';

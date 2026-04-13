<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('db:reset-sqlite', function (): int {
    $dir = database_path();
    foreach (glob($dir.DIRECTORY_SEPARATOR.'database.sqlite*') ?: [] as $file) {
        @unlink($file);
    }
    touch(database_path('database.sqlite'));
    $this->info('Removed database.sqlite and sidecars; created an empty database.sqlite.');

    return 0;
})->purpose('Delete SQLite DB and -wal/-shm so migrate is not blocked by stale journal files');

<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('sail:background-command-probe', function () {
    sleep(2);

    file_put_contents(storage_path('app/sail-background-command-probe'), 'completed');
});

Schedule::command('sail:background-command-probe')
    ->everyMinute()
    ->runInBackground();

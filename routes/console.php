<?php

use Illuminate\Support\Facades\Schedule;

/*
| Phase 1 onward: drain the database queue every minute. On Hostinger, point a
| cron job at `php artisan schedule:run` (every minute) and this keeps the CFDI
| ingest (and later PDF extraction) jobs moving without a long-running worker.
*/
Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=1')
    ->everyMinute()
    ->withoutOverlapping();

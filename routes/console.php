<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Schedule::command('notifora:prune')->dailyAt('02:30')->onOneServer()->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('notifora:billing-sweep')->hourly()->onOneServer()->withoutOverlapping();

// Backup database harian + pemantauan (email ke operator bila worker/scheduler/backup bermasalah)
if (config('notifora.backup.enabled')) {
    Schedule::command('notifora:backup')->dailyAt('03:15')->onOneServer()->withoutOverlapping();
}
Schedule::command('notifora:watchdog --quiet-ok')->everyFiveMinutes()->onOneServer()->withoutOverlapping();

// Hosting tanpa daemon: worker singkat tiap menit (WORKER_CRON_FALLBACK=true)
if (config('notifora.workers.cron_fallback')) {
    Schedule::command('queue:work --stop-when-empty --max-time=55 --queue='.implode(',', config('notifora.queues')))
        ->everyMinute()->withoutOverlapping(2)->name('cron-worker');
}

// Denyut scheduler untuk Admin → Operations (berjalan tiap menit)
Schedule::call(fn () => Cache::put('scheduler:heartbeat', now()->timestamp, 86400))->everyMinute()->name('heartbeat');

<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Schedule::command('notifora:prune')->dailyAt('02:30')->onOneServer()->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('notifora:billing-sweep')->hourly()->onOneServer()->withoutOverlapping();

// Denyut scheduler untuk Admin → Operations (berjalan tiap menit)
Schedule::call(fn () => Cache::put('scheduler:heartbeat', now()->timestamp, 86400))->everyMinute()->name('heartbeat');

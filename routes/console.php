<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('notifora:prune')->dailyAt('02:30')->onOneServer()->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('notifora:billing-sweep')->hourly()->onOneServer()->withoutOverlapping();

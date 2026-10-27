<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Cache;
use Throwable;

/** Menulis denyut per antrean setiap worker berputar atau memproses job (dibatasi 1x / 5 detik per antrean). */
class RecordWorkerHeartbeat
{
    private static array $last = [];

    public function handle(object $event): void
    {
        $queues = isset($event->job)
            ? [$event->job->getQueue()]
            : explode(',', (string) ($event->queue ?? ''));

        foreach (array_filter($queues) as $queue) {
            if ((self::$last[$queue] ?? 0) > time() - 5) {
                continue;
            }
            self::$last[$queue] = time();
            try {
                Cache::put("worker:beat:{$queue}", time(), 600);
            } catch (Throwable) {
                // cache mati tidak boleh mematikan worker
            }
        }
    }
}

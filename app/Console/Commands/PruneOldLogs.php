<?php

namespace App\Console\Commands;

use App\Models\Environment;
use App\Models\NotificationEvent;
use App\Models\WebhookDelivery;
use Illuminate\Console\Command;

class PruneOldLogs extends Command
{
    protected $signature = 'notifora:prune';

    protected $description = 'Hapus event/log yang melewati masa retensi plan workspace.';

    public function handle(): int
    {
        $total = 0;

        Environment::with('workspace')->each(function (Environment $env) use (&$total) {
            $days = (int) ($env->workspace->planConfig()['retention_days'] ?? 7);
            $cutoff = now()->subDays($days);

            // FK cascade menghapus executions, steps, message_logs, delivery_attempts.
            do {
                $ids = NotificationEvent::withoutGlobalScopes()
                    ->where('environment_id', $env->id)->where('created_at', '<', $cutoff)
                    ->limit(5000)->pluck('id');
                if ($ids->isNotEmpty()) {
                    NotificationEvent::withoutGlobalScopes()->whereIn('id', $ids)->delete();
                    $total += $ids->count();
                }
            } while ($ids->count() === 5000);
        });

        WebhookDelivery::where('created_at', '<', now()->subDays(30))->delete();
        $this->info("Dihapus: {$total} event beserta log terkait.");

        return self::SUCCESS;
    }
}

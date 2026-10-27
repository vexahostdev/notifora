<?php

namespace App\Console\Commands;

use App\Services\Billing\BillingService;
use Illuminate\Console\Command;

class BillingSweep extends Command
{
    protected $signature = 'notifora:billing-sweep';

    protected $description = 'Kedaluwarsakan invoice lama, kirim pengingat masa berlaku, mulai/akhiri masa tenggang plan.';

    public function handle(BillingService $billing): int
    {
        $r = $billing->sweep();
        $this->info("Invoice kedaluwarsa: {$r['expired']}, pengingat: {$r['reminders']}, mulai tenggang: {$r['graceStarted']}, turun ke Free: {$r['downgraded']}");

        return self::SUCCESS;
    }
}

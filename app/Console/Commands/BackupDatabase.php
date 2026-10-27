<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackup;
use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    protected $signature = 'notifora:backup {--keep= : Jumlah backup yang disimpan (default config)}';

    protected $description = 'Backup database ke storage/app/backups (gzip) dan hapus yang lama';

    public function handle(DatabaseBackup $backup): int
    {
        try {
            $name = $backup->run();
        } catch (\Throwable $e) {
            $this->error('Backup gagal: '.$e->getMessage());

            return self::FAILURE;
        }
        $pruned = $backup->prune((int) ($this->option('keep') ?: config('notifora.backup.keep')));
        $this->info("Backup selesai: $name".($pruned ? " ($pruned lama dihapus)" : ''));

        return self::SUCCESS;
    }
}

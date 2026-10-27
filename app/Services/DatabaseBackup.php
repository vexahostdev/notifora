<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

/** Backup database ke storage/app/backups (gzip). MySQL via mysqldump, SQLite via salinan berkas. */
class DatabaseBackup
{
    public function dir(): string
    {
        $d = config('notifora.backup.path') ?: storage_path('app/backups');
        File::ensureDirectoryExists($d);

        return $d;
    }

    /** @return array<int, array{name:string, size:int, at:int}> terbaru dulu */
    public function list(): array
    {
        return collect(File::files($this->dir()))
            ->filter(fn ($f) => preg_match('/^notifora-.+\.(sql|sqlite)\.gz$/', $f->getFilename()))
            ->map(fn ($f) => ['name' => $f->getFilename(), 'size' => $f->getSize(), 'at' => $f->getMTime()])
            ->sortByDesc('at')->values()->all();
    }

    public function path(string $name): ?string
    {
        if (! preg_match('/^notifora-[A-Za-z0-9_.\-]+\.(sql|sqlite)\.gz$/', $name)) {
            return null;
        }
        $p = $this->dir().DIRECTORY_SEPARATOR.$name;

        return is_file($p) ? $p : null;
    }

    public function delete(string $name): bool
    {
        $p = $this->path($name);

        return $p ? @unlink($p) : false;
    }

    public function prune(int $keep): int
    {
        $n = 0;
        foreach (array_slice($this->list(), max(1, $keep)) as $old) {
            $n += $this->delete($old['name']) ? 1 : 0;
        }

        return $n;
    }

    /** @return string nama berkas hasil backup */
    public function run(): string
    {
        $conn = config('database.default');
        $cfg = config("database.connections.$conn");
        $stamp = now()->format('Ymd-His');

        try {
            if (($cfg['driver'] ?? null) === 'sqlite') {
                $name = "notifora-{$stamp}.sqlite.gz";
                $src = $cfg['database'];
                if ($src === ':memory:' || ! is_file($src)) {
                    throw new RuntimeException('Database SQLite bukan berkas (memory) sehingga tidak dapat dibackup.');
                }
                $this->gzipFile($src, $this->dir().DIRECTORY_SEPARATOR.$name);
            } elseif (in_array($cfg['driver'] ?? null, ['mysql', 'mariadb'], true)) {
                $name = "notifora-{$stamp}.sql.gz";
                $this->mysqldump($cfg, $this->dir().DIRECTORY_SEPARATOR.$name);
            } else {
                throw new RuntimeException('Driver database "'.($cfg['driver'] ?? '?').'" belum didukung untuk backup otomatis.');
            }
        } catch (\Throwable $e) {
            Cache::put('backup:last_error', mb_substr($e->getMessage(), 0, 300), 86400 * 7);
            throw $e;
        }

        Cache::put('backup:last_ok', time(), 86400 * 30);
        Cache::forget('backup:last_error');

        return $name;
    }

    private function gzipFile(string $src, string $dest): void
    {
        $in = fopen($src, 'rb');
        $out = gzopen($dest, 'wb6');
        while (! feof($in)) {
            gzwrite($out, fread($in, 1048576));
        }
        fclose($in);
        gzclose($out);
    }

    private function mysqldump(array $cfg, string $dest): void
    {
        $cmd = [
            config('notifora.backup.mysqldump'), '--single-transaction', '--quick', '--routines', '--no-tablespaces',
            '-h', (string) $cfg['host'], '-P', (string) $cfg['port'], '-u', (string) $cfg['username'], (string) $cfg['database'],
        ];
        $p = new Process($cmd, null, ['MYSQL_PWD' => (string) ($cfg['password'] ?? '')], null, 600);
        $out = gzopen($dest, 'wb6');
        $bytes = 0;
        try {
            $p->run(function ($type, $buffer) use ($out, &$bytes) {
                if ($type === Process::OUT) {
                    $bytes += strlen($buffer);
                    gzwrite($out, $buffer);
                }
            });
        } finally {
            gzclose($out);
        }

        if (! $p->isSuccessful() || $bytes < 200) {
            @unlink($dest);
            throw new RuntimeException('mysqldump gagal: '.mb_substr(trim($p->getErrorOutput()) ?: 'keluaran kosong (binary tidak ditemukan?)', 0, 250));
        }
    }
}

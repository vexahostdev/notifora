<?php

namespace App\Services;

use App\Models\Plan;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Katalog plan: nilai default di config/notifora.php, dapat ditimpa/ditambah dari tabel `plans`
 * (diedit di Admin → Plans) tanpa deploy. `apply()` menggabungkan keduanya ke config saat boot.
 */
class PlanCatalog
{
    public const FREE = 'free';

    public static function apply(): void
    {
        try {
            $rows = Cache::remember('plans.db', 60, fn () => Plan::query()->orderBy('sort')->get()->map->getAttributes()->all());
        } catch (Throwable) {
            return; // tabel belum ada (mis. saat migrate)
        }
        if (! $rows) {
            return;
        }

        $plans = config('notifora.plans');
        $free = config('notifora.free_plan');

        foreach ($rows as $r) {
            $data = [
                'label' => $r['label'],
                'price' => (int) $r['price'],
                'events' => (int) $r['events'],
                'rpm' => (int) $r['rpm'],
                'retention_days' => (int) $r['retention_days'],
                'active' => (bool) $r['active'],
                'sort' => (int) $r['sort'],
            ];
            if ($r['key'] === self::FREE) {
                $free = $data + $free;
            } else {
                $plans[$r['key']] = $data + ($plans[$r['key']] ?? []);
            }
        }

        // Urut: sort kemudian harga
        uasort($plans, fn ($a, $b) => [($a['sort'] ?? 0), $a['price']] <=> [($b['sort'] ?? 0), $b['price']]);

        config(['notifora.plans' => $plans, 'notifora.free_plan' => $free]);
    }

    public static function forget(): void
    {
        Cache::forget('plans.db');
    }

    /** Semua plan berbayar (termasuk yang dinonaktifkan). */
    public static function all(): array
    {
        return config('notifora.plans');
    }

    /** Plan yang boleh dibeli / ditampilkan ke publik. */
    public static function purchasable(): array
    {
        return array_filter(config('notifora.plans'), fn ($p) => $p['active'] ?? true);
    }
}

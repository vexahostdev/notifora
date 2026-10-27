<?php

namespace App\Services\Providers;

use Illuminate\Support\Facades\Cache;

/** 5 kegagalan dalam 60 detik -> buka 60 detik. */
class CircuitBreaker
{
    /** @param ?\Closure $onOpen dipanggil sekali saat breaker terbuka */
    public function __construct(private string $key, private int $threshold = 5, private int $window = 60, private ?\Closure $onOpen = null) {}

    public function isOpen(): bool
    {
        return Cache::has("cb:open:{$this->key}");
    }

    public function recordFailure(): void
    {
        Cache::add("cb:fail:{$this->key}", 0, $this->window);
        if (Cache::increment("cb:fail:{$this->key}") >= $this->threshold) {
            Cache::put("cb:open:{$this->key}", 1, $this->window);
            Cache::forget("cb:fail:{$this->key}");
            if ($this->onOpen) {
                ($this->onOpen)();
            }
        }
    }

    public function recordSuccess(): void
    {
        Cache::forget("cb:fail:{$this->key}");
    }
}

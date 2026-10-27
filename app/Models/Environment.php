<?php

namespace App\Models;

use App\Enums\EnvironmentType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Environment extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['type' => EnvironmentType::class];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function isProduction(): bool
    {
        return $this->type === EnvironmentType::PRODUCTION;
    }

    /** Jalankan callback dengan environment aktif (dipakai job/worker). */
    public static function run(string $id, \Closure $fn): mixed
    {
        $previous = app()->bound('current.environment') ? app('current.environment') : null;
        $env = static::with('workspace')->findOrFail($id);
        app()->instance('current.environment', $env);

        try {
            return $fn($env);
        } finally {
            if ($previous) {
                app()->instance('current.environment', $previous);
            } else {
                app()->forgetInstance('current.environment');
            }
        }
    }
}

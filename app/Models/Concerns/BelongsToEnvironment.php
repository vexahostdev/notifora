<?php

namespace App\Models\Concerns;

use App\Models\Environment;
use App\Models\Scopes\EnvironmentScope;

trait BelongsToEnvironment
{
    public static function bootBelongsToEnvironment(): void
    {
        static::addGlobalScope(new EnvironmentScope);

        static::creating(function ($model) {
            if (empty($model->environment_id) && app()->bound('current.environment')) {
                $model->environment_id = app('current.environment')->id;
            }
        });
    }

    public function environment()
    {
        return $this->belongsTo(Environment::class);
    }
}

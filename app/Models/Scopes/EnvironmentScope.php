<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class EnvironmentScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (app()->bound('current.environment')) {
            $builder->where($model->getTable().'.environment_id', app('current.environment')->id);
        }
    }
}

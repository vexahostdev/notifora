<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['expires_at' => 'datetime', 'accepted_at' => 'datetime'];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class DeliveryAttempt extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['raw_trace' => 'array', 'success' => 'boolean', 'retryable' => 'boolean', 'created_at' => 'datetime'];
}

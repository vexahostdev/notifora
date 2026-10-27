<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PaymentEvent extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected $casts = ['payload' => 'array', 'processed_at' => 'datetime'];
}

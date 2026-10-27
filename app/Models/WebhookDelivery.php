<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['payload' => 'array', 'next_retry_at' => 'datetime', 'created_at' => 'datetime'];

    public function subscription()
    {
        return $this->belongsTo(WebhookSubscription::class, 'webhook_subscription_id');
    }
}

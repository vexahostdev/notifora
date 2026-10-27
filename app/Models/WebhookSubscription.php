<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEnvironment;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class WebhookSubscription extends Model
{
    use BelongsToEnvironment, HasUuids;

    protected $guarded = [];

    protected $hidden = ['secret'];

    protected $casts = ['events' => 'array', 'secret' => 'encrypted', 'disabled_at' => 'datetime'];

    public function deliveries()
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}

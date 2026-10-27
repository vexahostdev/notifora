<?php

namespace App\Models;

use App\Enums\ChannelType;
use App\Models\Concerns\BelongsToEnvironment;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    use BelongsToEnvironment, HasUuids;

    protected $guarded = [];

    protected $casts = ['data' => 'array'];

    public function preferences()
    {
        return $this->hasMany(SubscriberPreference::class);
    }

    public function inboxMessages()
    {
        return $this->hasMany(InAppMessage::class);
    }

    public function destinationFor(ChannelType $channel): ?string
    {
        return match ($channel) {
            ChannelType::WHATSAPP => $this->phone,
            ChannelType::EMAIL => $this->email,
            ChannelType::TELEGRAM => $this->telegram_chat_id,
            ChannelType::IN_APP => $this->external_id,
        };
    }

    /** Konteks untuk template: {{ subscriber.* }} */
    public function templateContext(): array
    {
        return [
            ...($this->data ?? []),
            'id' => $this->external_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => trim(($this->first_name ?? '').' '.($this->last_name ?? '')),
            'email' => $this->email,
            'phone' => $this->phone,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
        ];
    }
}

<?php

namespace App\Services;

use App\Models\Subscriber;
use App\Support\Phone;
use Illuminate\Database\UniqueConstraintViolationException;

class SubscriberService
{
    /**
     * Upsert subscriber di environment aktif. Hanya field yang dikirim (non-null) yang diubah.
     *
     * @param  array<string,mixed>|string  $to
     */
    public function upsert(array|string $to): Subscriber
    {
        $to = is_string($to) ? ['subscriberId' => $to] : $to;
        $externalId = (string) $to['subscriberId'];

        $subscriber = Subscriber::firstOrNew(['external_id' => $externalId]);
        $this->apply($subscriber, $to);

        try {
            $subscriber->save();
        } catch (UniqueConstraintViolationException) {
            // Dua request paralel membuat subscriber yang sama: muat baris pemenang lalu terapkan ulang.
            $subscriber = Subscriber::where('external_id', $externalId)->firstOrFail();
            $this->apply($subscriber, $to);
            $subscriber->save();
        }

        return $subscriber;
    }

    private function apply(Subscriber $subscriber, array $to): void
    {
        $map = [
            'email' => $to['email'] ?? null,
            'phone' => array_key_exists('phone', $to) ? Phone::normalize($to['phone']) : null,
            'telegram_chat_id' => isset($to['telegramChatId']) ? (string) $to['telegramChatId'] : null,
            'first_name' => $to['firstName'] ?? null,
            'last_name' => $to['lastName'] ?? null,
            'locale' => $to['locale'] ?? null,
            'timezone' => $to['timezone'] ?? null,
        ];
        foreach ($map as $column => $value) {
            if ($value !== null) {
                $subscriber->{$column} = $value;
            }
        }
        if (isset($to['data']) && is_array($to['data'])) {
            $subscriber->data = [...($subscriber->data ?? []), ...$to['data']];
        }
    }
}

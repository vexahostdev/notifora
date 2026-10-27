<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TriggerEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Aturan dipakai juga oleh endpoint bulk (dengan prefix). */
    public static function rulesFor(string $p = ''): array
    {
        return [
            $p.'workflow' => ['required', 'string', 'max:100'],
            $p.'to' => ['required', 'array'],
            $p.'to.subscriberId' => ['required', 'string', 'max:191'],
            $p.'to.email' => ['nullable', 'email', 'max:255'],
            $p.'to.phone' => ['nullable', 'string', 'max:32'],
            $p.'to.telegramChatId' => ['nullable', 'string', 'max:64'],
            $p.'to.firstName' => ['nullable', 'string', 'max:100'],
            $p.'to.lastName' => ['nullable', 'string', 'max:100'],
            $p.'to.locale' => ['nullable', 'string', 'max:10'],
            $p.'to.timezone' => ['nullable', 'timezone'],
            $p.'to.data' => ['nullable', 'array'],
            $p.'payload' => ['nullable', 'array'],
            $p.'overrides' => ['nullable', 'array'],
        ];
    }

    /** `to` boleh berupa string subscriberId saja. */
    public static function normalize(array $input): array
    {
        if (isset($input['to']) && is_string($input['to'])) {
            $input['to'] = ['subscriberId' => $input['to']];
        }

        return $input;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(self::normalize($this->all()));
    }

    public function rules(): array
    {
        return self::rulesFor();
    }
}

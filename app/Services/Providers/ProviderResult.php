<?php

namespace App\Services\Providers;

final class ProviderResult
{
    public function __construct(
        public readonly bool $success,
        public readonly bool $retryable = false,
        public readonly ?string $messageId = null,
        public readonly ?string $destination = null,
        public readonly ?string $error = null,
        public readonly ?string $errorCode = null,
        public readonly ?int $httpStatus = null,
        public readonly array $rawResponse = [],
        public readonly bool $delivered = false,   // true bila langsung terkirim (In-App)
        public readonly bool $deferred = false,    // true bila circuit breaker terbuka: jadwal ulang tanpa hitung percobaan
    ) {}

    /** Trace aman untuk disimpan: rahasia disamarkan. */
    public function redactedTrace(): array
    {
        return [
            'http_status' => $this->httpStatus,
            'error_code' => $this->errorCode,
            'error' => $this->error ? mb_substr($this->error, 0, 500) : null,
            'response' => self::redact($this->rawResponse),
        ];
    }

    public static function redact(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_string($k) && preg_match('/token|key|secret|authorization|password/i', $k)) {
                $data[$k] = '[redacted]';
            } elseif (is_array($v)) {
                $data[$k] = self::redact($v);
            }
        }

        return $data;
    }
}

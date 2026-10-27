<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    public static function ok(mixed $data, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => ['request_id' => self::requestId(), ...$meta],
        ], $status);
    }

    public static function error(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        return response()->json([
            'error' => array_filter([
                'code' => $code,
                'message' => $message,
                'details' => $details ?: null,
                'request_id' => self::requestId(),
            ], fn ($v) => $v !== null),
        ], $status);
    }

    private static function requestId(): ?string
    {
        return request()?->attributes->get('request_id');
    }
}

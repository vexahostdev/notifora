<?php

namespace App\Services;

use App\Models\Environment;
use App\Models\Subscriber;

/** JWT HS256 minimal untuk widget inbox (diverifikasi oleh socket-engine). */
class InboxTokenService
{
    public function issue(Environment $env, Subscriber $subscriber): string
    {
        $now = time();

        return $this->encode([
            'sub' => $subscriber->id,
            'env' => $env->id,
            'iat' => $now,
            'exp' => $now + (int) config('notifora.socket.token_ttl'),
        ]);
    }

    /** @return array<string,mixed>|null */
    public function verify(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$h, $p, $s] = $parts;
        if (! hash_equals($this->sign("$h.$p"), $s)) {
            return null;
        }
        $claims = json_decode($this->b64d($p), true);

        return is_array($claims) && ($claims['exp'] ?? 0) > time() ? $claims : null;
    }

    private function encode(array $claims): string
    {
        $h = $this->b64e(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $p = $this->b64e(json_encode($claims));

        return "$h.$p.".$this->sign("$h.$p");
    }

    private function sign(string $data): string
    {
        $secret = (string) config('notifora.socket.jwt_secret');
        if ($secret === '') {
            throw new \RuntimeException('SOCKET_JWT_SECRET belum dikonfigurasi.');
        }

        return $this->b64e(hash_hmac('sha256', $data, $secret, true));
    }

    private function b64e(string $v): string
    {
        return rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
    }

    private function b64d(string $v): string
    {
        return base64_decode(strtr($v, '-_', '+/')) ?: '';
    }
}

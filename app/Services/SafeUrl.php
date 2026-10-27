<?php

namespace App\Services;

/** Proteksi SSRF untuk URL webhook klien. */
class SafeUrl
{
    /**
     * @return array{ok:bool, reason:?string, host:?string, ip:?string, port:int}
     */
    public static function inspect(string $url): array
    {
        $p = parse_url($url);
        $fail = fn (string $r) => ['ok' => false, 'reason' => $r, 'host' => null, 'ip' => null, 'port' => 0];

        if (! $p || empty($p['host']) || empty($p['scheme'])) {
            return $fail('URL tidak valid');
        }
        $scheme = strtolower($p['scheme']);
        $allowHttp = app()->environment(['local', 'development', 'testing']);
        if ($scheme !== 'https' && ! ($scheme === 'http' && $allowHttp)) {
            return $fail('Hanya https yang diizinkan');
        }
        $host = $p['host'];
        $port = $p['port'] ?? ($scheme === 'https' ? 443 : 80);

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : null;
        if ($ips === null) {
            if (! config('notifora.ssrf_resolve', true)) {
                return ['ok' => true, 'reason' => null, 'host' => $host, 'ip' => null, 'port' => $port];
            }
            $ips = gethostbynamel($host) ?: [];
        }
        if (! $ips) {
            return $fail('Host tidak dapat di-resolve');
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                if (! $allowHttp) {
                    return $fail('Alamat IP privat/reserved ditolak');
                }
            }
        }

        return ['ok' => true, 'reason' => null, 'host' => $host, 'ip' => $ips[0], 'port' => $port];
    }
}

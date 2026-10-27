<?php

namespace App\Services;

use Carbon\Carbon;
use Throwable;

/**
 * Parser template aman: hanya {{ path|filter:arg }}. Tidak ada eval/Blade.
 */
class TemplateCompiler
{
    public const ROOTS = ['payload', 'subscriber', 'event', 'workspace'];

    public const FILTERS = ['upper', 'lower', 'title', 'trim', 'default', 'truncate', 'currency', 'number', 'date', 'e', 'json'];

    public const MAX_TEMPLATE = 20000;

    public const MAX_OUTPUT = 100000;

    private const TOKEN = '/\{\{\s*(.+?)\s*\}\}/s';

    /** @return string[] daftar error sintaks (kosong = valid) */
    public function validate(string $template): array
    {
        $errors = [];
        if (strlen($template) > self::MAX_TEMPLATE) {
            $errors[] = 'Template melebihi '.self::MAX_TEMPLATE.' karakter.';
        }
        if (substr_count($template, '{{') !== substr_count($template, '}}')) {
            $errors[] = 'Jumlah "{{" dan "}}" tidak seimbang.';
        }
        preg_match_all(self::TOKEN, $template, $m);
        foreach ($m[1] as $expr) {
            try {
                $this->parseExpression($expr);
            } catch (\InvalidArgumentException $e) {
                $errors[] = $e->getMessage().' ('.$expr.')';
            }
        }

        return $errors;
    }

    /**
     * @param  array<string,mixed>  $context  root: payload, subscriber, event, workspace
     * @param  string[]  $warnings  diisi variabel yang tidak ditemukan
     */
    public function render(string $template, array $context, bool $escapeHtml = false, array &$warnings = []): string
    {
        $out = preg_replace_callback(self::TOKEN, function ($m) use ($context, $escapeHtml, &$warnings) {
            try {
                [$path, $filters] = $this->parseExpression($m[1]);
            } catch (\InvalidArgumentException) {
                $warnings[] = 'invalid_expression: '.$m[1];

                return '';
            }

            $value = data_get($context, $path);
            if ($value === null) {
                $warnings[] = 'missing: '.$path;
            }
            $value = $this->applyFilters($value, $filters, $context);
            $string = is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);

            return $escapeHtml ? e($string) : $string;
        }, $template);

        return strlen($out) > self::MAX_OUTPUT ? substr($out, 0, self::MAX_OUTPUT) : $out;
    }

    /** @return array{0:string,1:array<int,array{0:string,1:mixed}>} */
    private function parseExpression(string $expr): array
    {
        $parts = preg_split('/\|(?=(?:[^"]*"[^"]*")*[^"]*$)/', $expr);
        $path = trim(array_shift($parts));

        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z0-9_\-]+)*$/', $path)) {
            throw new \InvalidArgumentException('Path variabel tidak valid');
        }
        if (! in_array(explode('.', $path)[0], self::ROOTS, true)) {
            throw new \InvalidArgumentException('Root variabel tidak diizinkan (gunakan '.implode(', ', self::ROOTS).')');
        }

        $filters = [];
        foreach ($parts as $part) {
            $part = trim($part);
            $name = $part;
            $arg = null;
            if (str_contains($part, ':')) {
                [$name, $rawArg] = explode(':', $part, 2);
                $name = trim($name);
                $rawArg = trim($rawArg);
                $arg = (str_starts_with($rawArg, '"') && str_ends_with($rawArg, '"') && strlen($rawArg) >= 2)
                    ? substr($rawArg, 1, -1)
                    : (is_numeric($rawArg) ? $rawArg + 0 : $rawArg);
            }
            if (! in_array($name, self::FILTERS, true)) {
                throw new \InvalidArgumentException('Filter tidak dikenal: '.$name);
            }
            $filters[] = [$name, $arg];
        }

        return [$path, $filters];
    }

    private function applyFilters(mixed $value, array $filters, array $context): mixed
    {
        foreach ($filters as [$name, $arg]) {
            $value = match ($name) {
                'upper' => mb_strtoupper((string) $value),
                'lower' => mb_strtolower((string) $value),
                'title' => mb_convert_case((string) $value, MB_CASE_TITLE),
                'trim' => trim((string) $value),
                'default' => ($value === null || $value === '') ? $arg : $value,
                'truncate' => mb_strlen((string) $value) > (int) $arg ? mb_substr((string) $value, 0, max(0, (int) $arg - 1)).'…' : $value,
                'currency' => $this->currency($value, (string) ($arg ?? 'IDR')),
                'number' => is_numeric($value) ? number_format((float) $value, (int) ($arg ?? 0), ',', '.') : $value,
                'date' => $this->date($value, (string) ($arg ?? 'd M Y H:i'), $context),
                'e' => e((string) $value),
                'json' => json_encode($value, JSON_UNESCAPED_UNICODE),
            };
        }

        return $value;
    }

    private function currency(mixed $value, string $code): mixed
    {
        if (! is_numeric($value)) {
            return $value;
        }
        if (strtoupper($code) === 'IDR') {
            return 'Rp '.number_format((float) $value, 0, ',', '.');
        }

        return strtoupper($code).' '.number_format((float) $value, 2, '.', ',');
    }

    private function date(mixed $value, string $format, array $context): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }
        try {
            $c = is_numeric($value) ? Carbon::createFromTimestamp((int) $value) : Carbon::parse((string) $value);
            $tz = data_get($context, 'subscriber.timezone');
            if ($tz) {
                $c = $c->setTimezone($tz);
            }

            return $c->format($format);
        } catch (Throwable) {
            return $value;
        }
    }
}

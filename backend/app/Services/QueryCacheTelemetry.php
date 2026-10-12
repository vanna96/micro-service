<?php

namespace App\Services;

use Illuminate\Http\Request;

class QueryCacheTelemetry
{
    public const ATTRIBUTE = 'query_cache_telemetry';

    public function recordKey(mixed $key, string $result): void
    {
        if (! is_string($key) || ! str_contains($key, ':catalog:')) {
            return;
        }

        $this->record($result);
    }

    public function record(string $result, int $amount = 1): void
    {
        if (! app()->bound('request')) {
            return;
        }

        $request = app('request');
        if (! $request instanceof Request) {
            return;
        }

        $stats = (array) $request->attributes->get(self::ATTRIBUTE, []);
        $result = in_array($result, ['hit', 'miss', 'bypass', 'invalidation'], true)
            ? $result
            : 'bypass';
        $stats[$result] = (int) ($stats[$result] ?? 0) + max(1, $amount);
        $request->attributes->set(self::ATTRIBUTE, $stats);
    }

    /** @return array<string, int> */
    public function snapshot(?Request $request = null): array
    {
        $request ??= app()->bound('request') ? app('request') : null;

        return $request instanceof Request
            ? array_map('intval', (array) $request->attributes->get(self::ATTRIBUTE, []))
            : [];
    }
}

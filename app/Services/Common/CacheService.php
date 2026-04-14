<?php

namespace App\Services\Common;

use Illuminate\Support\Facades\Cache;

class CacheService
{
    public function getCachedResponse(string $key): ?array
    {
        if (!Cache::has($key)) {
            return null;
        }

        $payload = Cache::get($key, []);

        return [
            'data' => $payload['data'] ?? null,
            'metadata' => $payload['metadata'] ?? [],
            'isCached' => true,
        ];
    }

    public function setCachedResponse(string $key, mixed $data, array $metadata = []): array
    {
        Cache::put($key, [
            'data' => $data,
            'metadata' => $metadata,
        ], now()->addSeconds(config('dorar.cache_each_seconds', 5)));

        return [
            'data' => $data,
            'metadata' => $metadata,
            'isCached' => false,
        ];
    }
}

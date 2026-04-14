<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Services\Common\CacheService;

class DataService
{
    public function __construct(private readonly CacheService $cacheService)
    {
    }

    public function getData(string $file): array
    {
        $cacheKey = 'local-data:'.$file;
        $cached = $this->cacheService->getCachedResponse($cacheKey);
        if ($cached) {
            return $cached;
        }

        $path = base_path('data/'.$file.'.json');
        if (!file_exists($path)) {
            throw new ApiException('Resource not found', 404);
        }

        $content = file_get_contents($path);
        $data = json_decode((string) $content, true);

        if (!is_array($data)) {
            throw new ApiException('Error parsing response', 502);
        }

        return $this->cacheService->setCachedResponse($cacheKey, $data, [
            'length' => count($data),
        ]);
    }
}

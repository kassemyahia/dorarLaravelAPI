<?php

namespace App\Services\Dorar;

use App\Exceptions\ApiException;
use App\Services\Common\CacheService;
use App\Services\Common\DorarHttpService;

class MohdithSearchService
{
    public function __construct(
        private readonly DorarHttpService $httpService,
        private readonly CacheService $cacheService,
    ) {
    }

    public function getOneMohdithByIdUsingSiteDorar(string $mohdithId): array
    {
        if ($mohdithId === '') {
            throw new ApiException('Mohdith ID is required', 400);
        }

        $url = 'https://www.dorar.net/hadith/mhd/'.$mohdithId;
        $cached = $this->cacheService->getCachedResponse($url);
        if ($cached) {
            return $cached;
        }

        $xpath = $this->httpService->fetchDocument($url);
        $h4 = $xpath->query('//h4')?->item(0);
        if (!$h4) {
            throw new ApiException('Invalid response structure from Dorar', 502);
        }

        $infoNode = $h4->nextSibling;
        while ($infoNode && trim((string) $infoNode->textContent) === '') {
            $infoNode = $infoNode->nextSibling;
        }

        $result = [
            'name' => trim((string) $h4->textContent),
            'mohdithId' => $mohdithId,
            'info' => trim((string) ($infoNode?->textContent ?? '')),
        ];

        return $this->cacheService->setCachedResponse($url, $result, []);
    }
}

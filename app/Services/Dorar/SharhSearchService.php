<?php

namespace App\Services\Dorar;

use App\Exceptions\ApiException;
use App\Services\Common\CacheService;
use App\Services\Common\DorarHttpService;

class SharhSearchService
{
    public function __construct(
        private readonly DorarHttpService $httpService,
        private readonly CacheService $cacheService,
    ) {
    }

    private function serializeQueryParams(array $params): string
    {
        $parts = [];
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $entry) {
                    $parts[] = rawurlencode((string) $key).'[]='.rawurlencode((string) $entry);
                }
            } else {
                $parts[] = rawurlencode((string) $key).'='.rawurlencode((string) $value);
            }
        }

        return implode('&', $parts);
    }

    private function getSharhById(string $sharhId): array
    {
        if ($sharhId === '') {
            throw new ApiException('Sharh ID is required', 400);
        }

        $xpath = $this->httpService->fetchDocument('https://www.dorar.net/hadith/sharh/'.$sharhId);
        $article = $xpath->query('//article')?->item(0);
        if (!$article) {
            throw new ApiException('Invalid response structure from Dorar', 502);
        }

        $hadith = trim((string) preg_replace('/-\s*/u', '', (string) $article->textContent));
        $primaries = $xpath->query('//*[contains(@class,"primary-text-color")]') ?: [];

        $rawi = trim((string) ($primaries->item(0)?->textContent ?? ''));
        $mohdith = trim((string) ($primaries->item(1)?->textContent ?? ''));
        $book = trim((string) ($primaries->item(2)?->textContent ?? ''));
        $numberOrPage = trim((string) ($primaries->item(3)?->textContent ?? ''));
        $grade = trim((string) ($primaries->item(4)?->textContent ?? ''));
        $takhrij = trim((string) ($primaries->item(5)?->textContent ?? ''));

        $base = $xpath->query('//*[contains(@class,"text-justify")]')?->item(0);
        $sharhNode = $base?->nextSibling;
        while ($sharhNode && trim((string) $sharhNode->textContent) === '') {
            $sharhNode = $sharhNode->nextSibling;
        }

        if (!$sharhNode) {
            throw new ApiException('Sharh content not found', 404);
        }

        return [
            'hadith' => $hadith,
            'rawi' => $rawi,
            'mohdith' => $mohdith,
            'book' => $book,
            'numberOrPage' => $numberOrPage,
            'grade' => $grade,
            'takhrij' => $takhrij,
            'hasSharhMetadata' => true,
            'sharhMetadata' => [
                'id' => $sharhId,
                'isContainSharh' => true,
                'urlToGetSharhById' => '/v1/site/sharh/'.$sharhId,
                'sharh' => trim((string) $sharhNode->textContent),
            ],
        ];
    }

    public function getOneSharhByIdUsingSiteDorar(string $sharhId): array
    {
        $url = 'https://www.dorar.net/hadith/sharh/'.$sharhId;
        $cached = $this->cacheService->getCachedResponse($url);
        if ($cached) {
            return $cached;
        }

        $result = $this->getSharhById($sharhId);

        return $this->cacheService->setCachedResponse($url, $result, []);
    }

    public function getOneSharhByTextUsingSiteDorar(string $text, string $tab, bool $isForSpecialist): array
    {
        if ($text === '') {
            throw new ApiException('Text of sharh is required', 400);
        }

        $url = 'https://www.dorar.net/hadith/search?q='.rawurlencode($text).($tab === 'specialist' ? '&all' : '');
        $cached = $this->cacheService->getCachedResponse($url);
        if ($cached) {
            $cached['metadata']['specialist'] = $isForSpecialist;

            return $cached;
        }

        $xpath = $this->httpService->fetchDocument($url);
        $tabElement = $xpath->query('//*[@id="'.$tab.'"]')?->item(0);
        if (!$tabElement) {
            throw new ApiException('Invalid response structure from Dorar', 502);
        }

        $anchor = $xpath->query('.//a[@xplain]', $tabElement)?->item(0);
        $sharhId = trim((string) ($anchor?->attributes?->getNamedItem('xplain')?->nodeValue ?? ''));
        if ($sharhId === '') {
            throw new ApiException('No sharh found for the given text', 404);
        }

        $result = $this->getSharhById($sharhId);

        return $this->cacheService->setCachedResponse($url, $result, [
            'specialist' => $isForSpecialist,
        ]);
    }

    public function getAllSharhUsingSiteDorar(array $queryParams, string $tab, bool $isRemoveHtml, bool $isForSpecialist): array
    {
        $query = str_replace('value=', 'q=', $this->serializeQueryParams($queryParams));
        $url = 'https://www.dorar.net/hadith/search?'.$query.($tab === 'specialist' ? '&all' : '');

        $cached = $this->cacheService->getCachedResponse($url);
        if ($cached) {
            return $cached;
        }

        $xpath = $this->httpService->fetchDocument($url);
        $tabElement = $xpath->query('//*[@id="'.$tab.'"]')?->item(0);
        if (!$tabElement) {
            throw new ApiException('Invalid response structure from Dorar', 502);
        }

        $blocks = $xpath->query('.//*[contains(@class,"border-bottom")]', $tabElement) ?: [];
        $sharhIds = [];
        foreach ($blocks as $block) {
            $anchor = $xpath->query('.//a[@xplain]', $block)?->item(0);
            $id = trim((string) ($anchor?->attributes?->getNamedItem('xplain')?->nodeValue ?? ''));
            if ($id !== '' && $id !== '0') {
                $sharhIds[] = $id;
            }
        }

        if (count($sharhIds) === 0) {
            return [
                'data' => [],
                'metadata' => [
                    'length' => 0,
                    'page' => (int) ($queryParams['page'] ?? 1),
                    'removeHTML' => $isRemoveHtml,
                    'specialist' => $isForSpecialist,
                ],
                'isCached' => false,
            ];
        }

        $result = array_map(fn (string $id) => $this->getSharhById($id), $sharhIds);

        return $this->cacheService->setCachedResponse($url, $result, [
            'length' => count($result),
            'page' => (int) ($queryParams['page'] ?? 1),
            'removeHTML' => $isRemoveHtml,
            'specialist' => $isForSpecialist,
        ]);
    }
}

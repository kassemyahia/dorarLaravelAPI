<?php

namespace App\Services\Dorar;

use App\Exceptions\ApiException;
use App\Services\Common\CacheService;
use App\Services\Common\DorarHttpService;
use App\Services\Common\HtmlParserService;
use DOMElement;

class HadithSearchService
{
    public function __construct(
        private readonly DorarHttpService $httpService,
        private readonly CacheService $cacheService,
        private readonly HtmlParserService $parser,
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

    public function searchUsingAPIDorar(array $queryParams, bool $isRemoveHTML): array
    {
        $query = str_replace('value=', 'skey=', $this->serializeQueryParams($queryParams));
        $url = 'https://dorar.net/dorar_api.json?'.$query;

        $cached = $this->cacheService->getCachedResponse($url);
        if ($cached) {
            return $cached;
        }

        $data = $this->httpService->fetchJson($url);
        if (!is_array($data)) {
            throw new ApiException('Invalid response from Dorar API', 502);
        }

        $resultHtml = $data['ahadith']['result'] ?? null;
        if (!is_string($resultHtml) || $resultHtml === '') {
            throw new ApiException('Invalid response from Dorar API', 502);
        }

        $xpath = $this->httpService->toXPath(html_entity_decode($resultHtml, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $nodes = $xpath->query('//*[contains(@class,"hadith-info")]') ?: [];

        $result = [];
        foreach ($nodes as $node) {
            if (!($node instanceof DOMElement)) {
                continue;
            }

            $result[] = $this->parser->mapApiHadithInfo($xpath, $node, $isRemoveHTML);
        }

        if (count($result) === 0) {
            throw new ApiException('No hadith found in the response', 502);
        }

        $currentPage = (int) ($queryParams['page'] ?? 1);
        $hasNextPage = count($result) === (int) config('dorar.hadith_api_page_size', 15);

        return $this->cacheService->setCachedResponse($url, $result, [
            'length' => count($result),
            'currentPageCount' => count($result),
            'page' => $currentPage,
            'hasNextPage' => $hasNextPage,
            'hasPrevPage' => $currentPage > 1,
            'removeHTML' => $isRemoveHTML,
        ]);
    }

    public function searchUsingSiteDorar(array $queryParams, string $tab, bool $isRemoveHTML, bool $isForSpecialist): array
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

        $homeTab = $xpath->query('//a[@aria-controls="home"]')?->item(0);
        $specialistTab = $xpath->query('//a[@aria-controls="specialist"]')?->item(0);

        preg_match('/\d+/', (string) ($homeTab?->textContent ?? ''), $homeMatch);
        preg_match('/\d+/', (string) ($specialistTab?->textContent ?? ''), $specialistMatch);

        $numberOfNonSpecialist = isset($homeMatch[0]) ? (int) $homeMatch[0] : 0;
        $numberOfSpecialist = isset($specialistMatch[0]) ? (int) $specialistMatch[0] : 0;

        $blocks = $xpath->query('.//*[contains(@class,"border-bottom")]', $tabElement) ?: [];
        $result = [];
        foreach ($blocks as $block) {
            if (!($block instanceof DOMElement)) {
                continue;
            }

            $result[] = $this->parser->mapSiteHadithBlock($xpath, $block, [
                'removeHTML' => $isRemoveHTML,
                'hadithCleanRegex' => '/\d+\s+-/u',
            ]);
        }

        $currentPage = (int) ($queryParams['page'] ?? 1);
        $total = $isForSpecialist ? $numberOfSpecialist : $numberOfNonSpecialist;
        $totalPages = $total > 0 ? (int) ceil($total / (int) config('dorar.hadith_site_page_size', 30)) : 0;

        return $this->cacheService->setCachedResponse($url, $result, [
            'length' => count($result),
            'currentPageCount' => count($result),
            'total' => $total,
            'page' => $currentPage,
            'totalPages' => $totalPages,
            'hasNextPage' => $totalPages > 0 && $currentPage < $totalPages,
            'hasPrevPage' => $currentPage > 1,
            'removeHTML' => $isRemoveHTML,
            'specialist' => $isForSpecialist,
            'numberOfNonSpecialist' => $numberOfNonSpecialist,
            'numberOfSpecialist' => $numberOfSpecialist,
        ]);
    }

    public function getOneHadithUsingSiteDorarById(string $hadithId): array
    {
        $url = 'https://www.dorar.net/h/'.$hadithId;

        $cached = $this->cacheService->getCachedResponse($url);
        if ($cached) {
            return $cached;
        }

        $xpath = $this->httpService->fetchDocument($url);
        $info = $xpath->query('(//*[contains(@class,"border-bottom")])[1]')?->item(0);
        if (!($info instanceof DOMElement)) {
            throw new ApiException('Invalid response structure from Dorar', 502);
        }

        $result = $this->parser->mapSiteHadithBlock($xpath, $info, [
            'removeHTML' => true,
            'infoNode' => $info,
            'hadithCleanRegex' => '/-\s*\:?\s*/u',
        ]);

        return $this->cacheService->setCachedResponse($url, $result, ['length' => 1]);
    }

    public function getAllSimilarHadithUsingSiteDorar(string $similarId): array
    {
        $url = 'https://www.dorar.net/h/'.$similarId.'?sims=1';

        $cached = $this->cacheService->getCachedResponse($url);
        if ($cached) {
            return $cached;
        }

        $xpath = $this->httpService->fetchDocument($url);
        $blocks = $xpath->query('//*[contains(@class,"border-bottom")]') ?: [];

        $result = [];
        foreach ($blocks as $block) {
            if (!($block instanceof DOMElement)) {
                continue;
            }

            $result[] = $this->parser->mapSiteHadithBlock($xpath, $block, [
                'removeHTML' => true,
                'hadithCleanRegex' => '/-\s*\:?\s*/u',
            ]);
        }

        return $this->cacheService->setCachedResponse($url, $result, ['length' => count($result)]);
    }

    public function getAlternateHadithUsingSiteDorar(string $alternateId): array
    {
        $url = 'https://www.dorar.net/h/'.$alternateId.'?alts=1';

        $cached = $this->cacheService->getCachedResponse($url);
        if ($cached) {
            return $cached;
        }

        $xpath = $this->httpService->fetchDocument($url);
        $info = $xpath->query('(//*[contains(@class,"border-bottom")])[2]')?->item(0);
        if (!($info instanceof DOMElement)) {
            throw new ApiException('No alternate hadith found', 404);
        }

        $result = $this->parser->mapSiteHadithBlock($xpath, $info, [
            'removeHTML' => true,
            'hadithCleanRegex' => '/-\s*\:?\s*/u',
            'includeAlternate' => false,
        ]);

        return $this->cacheService->setCachedResponse($url, $result, []);
    }

    public function getUsulHadithUsingSiteDorar(string $usulId): array
    {
        $url = 'https://www.dorar.net/h/'.$usulId.'?osoul=1';

        $cached = $this->cacheService->getCachedResponse($url);
        if ($cached) {
            return $cached;
        }

        $xpath = $this->httpService->fetchDocument($url);
        $mainInfo = $xpath->query('(//*[contains(@class,"border-bottom")])[1]')?->item(0);
        if (!($mainInfo instanceof DOMElement)) {
            throw new ApiException('No usul hadith found', 404);
        }

        $baseResult = $this->parser->mapSiteHadithBlock($xpath, $mainInfo, [
            'removeHTML' => true,
            'hadithCleanRegex' => '/-\s*\:?\s*/u',
        ]);

        $sources = $this->parser->extractUsulSources($xpath);

        $result = array_merge($baseResult, [
            'hasUsulHadith' => true,
            'usulHadith' => [
                'sources' => $sources,
                'count' => count($sources),
            ],
        ]);

        return $this->cacheService->setCachedResponse($url, $result, [
            'length' => 1,
            'usulSourcesCount' => count($sources),
        ]);
    }
}

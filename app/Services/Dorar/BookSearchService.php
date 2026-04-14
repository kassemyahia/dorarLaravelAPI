<?php

namespace App\Services\Dorar;

use App\Exceptions\ApiException;
use App\Services\Common\CacheService;
use App\Services\Common\DorarHttpService;

class BookSearchService
{
    public function __construct(
        private readonly DorarHttpService $httpService,
        private readonly CacheService $cacheService,
    ) {
    }

    public function getOneBookByIdUsingSiteDorar(string $bookId): array
    {
        if ($bookId === '') {
            throw new ApiException('Book ID is required', 400);
        }

        $url = 'https://www.dorar.net/hadith/book-card/'.$bookId;
        $cached = $this->cacheService->getCachedResponse($url);
        if ($cached) {
            return $cached;
        }

        $response = $this->httpService->fetchJson($url);
        if (!is_string($response)) {
            throw new ApiException('Invalid response structure from Dorar', 502);
        }

        $html = html_entity_decode($response, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $xpath = $this->httpService->toXPath($html);

        $h5 = $xpath->query('//h5')?->item(0);
        if (!$h5) {
            throw new ApiException('Invalid response structure from Dorar', 502);
        }

        $name = trim((string) preg_replace('/^\d+\s-\s*/u', '', (string) $h5->textContent));
        $spans = $xpath->query('//span') ?: [];

        $author = trim((string) ($spans->item(0)?->textContent ?? ''));
        $reviewer = trim((string) ($spans->item(1)?->textContent ?? ''));
        $publisher = trim((string) ($spans->item(2)?->textContent ?? ''));
        $edition = trim((string) ($spans->item(3)?->textContent ?? ''));
        $editionYearRaw = trim((string) ($spans->item(4)?->textContent ?? ''));

        preg_match('/^\d+/', $editionYearRaw, $editionYearMatch);

        $result = [
            'name' => $name,
            'bookId' => $bookId,
            'author' => $author,
            'reviewer' => $reviewer,
            'publisher' => $publisher,
            'edition' => $edition,
            'editionYear' => $editionYearMatch[0] ?? '',
        ];

        return $this->cacheService->setCachedResponse($url, $result, []);
    }
}

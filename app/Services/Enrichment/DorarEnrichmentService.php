<?php

namespace App\Services\Enrichment;

use App\Exceptions\ApiException;
use App\Models\HadithNormalizedCache;
use App\Services\Dorar\HadithSearchService;
use App\Services\Dorar\SharhSearchService;

class DorarEnrichmentService
{
    private float $lastRequestAt = 0;

    private SharhSearchService $sharh;

    private DorarBookMap $books;

    public function __construct(private readonly HadithSearchService $search, private readonly ArabicNormalizer $normalizer, private readonly HadithMatcherService $matcher, ?SharhSearchService $sharh = null, ?DorarBookMap $books = null)
    {
        $this->sharh = $sharh ?? app(SharhSearchService::class);
        $this->books = $books ?? new DorarBookMap;
    }

    public function enrichHadith(string $hadithText, ?string $bookId = null, int $delayMs = 5000, float $lowThreshold = .8, float $highThreshold = .95, ?string $bookName = null, int|string|null $number = null): array
    {
        $normalized = $this->normalizer->normalizeAndHash($hadithText);
        if ($cached = HadithNormalizedCache::where('normalized_hash', $normalized['hash'])->where('matched', true)->first()) {
            return $this->result($cached->matched, $cached->confidence, $cached->needs_review, null, null, $cached->dorar_result, true);
        }
        $this->delay($delayMs);
        try {
            $params = ['value' => $this->searchText($hadithText)];
            if ($source = $this->books->verifiedDorarSourceId($bookId)) {
                $params['s'] = [$source];
            }
            $response = $this->search->searchUsingSiteDorar($params, 'home', true, false);
            $candidates = $response['data'] ?? [];
            if ($candidates === []) {
                return $this->result(false, 0, false, 'NOT_FOUND', 'No Dorar results found');
            }
            $selection = $this->matcher->bestCandidate($hadithText, $candidates, ['book_aliases' => $this->books->aliases($bookId, $bookName), 'number' => $number]);
            $best = $selection['best'];
            $match = $best['candidate'];
            $confidence = $best['score'];
            $matched = $confidence >= $lowThreshold;
            $review = $confidence < $highThreshold;
            $dorar = $this->emptyDorar();
            foreach (array_keys($dorar) as $key) {
                if ($key !== 'source') {
                    $dorar[$key] = $match[$key] ?? null;
                }
            }
            $sharhId = $match['sharhMetadata']['id'] ?? null;
            if ($sharhId) {
                try {
                    $detail = $this->sharh->getOneSharhByIdUsingSiteDorar((string) $sharhId);
                    $dorar['sharh'] = $detail['sharhMetadata']['sharh'] ?? null;
                } catch (\Throwable) {
                }
            }
            $result = $this->result($matched, $confidence, $review, $matched ? null : 'MATCH_LOW_CONFIDENCE', $matched ? null : 'Best result is below the configured threshold', $dorar);
            $result['diagnostics'] = $selection['diagnostics'];
            if ($matched) {
                HadithNormalizedCache::updateOrCreate(['normalized_hash' => $normalized['hash']], ['original_text' => $hadithText, 'dorar_result' => $dorar, 'matched' => true, 'confidence' => $confidence, 'needs_review' => $review, 'error_type' => null]);
            }

            return $result;
        } catch (ApiException $e) {
            $type = match (true) {
                $e->getCode() === 403 => 'FORBIDDEN', $e->getCode() === 429 => 'RATE_LIMITED', str_contains(strtolower($e->getMessage()), 'timeout') => 'TIMEOUT', str_contains(strtolower($e->getMessage()), 'structure'), str_contains(strtolower($e->getMessage()), 'parsing') => 'PARSING_FAILED', default => 'REQUEST_FAILED'
            };

            return $this->result(false, 0, true, $type, $e->getMessage());
        } catch (\Throwable $e) {
            return $this->result(false, 0, true, 'REQUEST_FAILED', $e->getMessage());
        }
    }

    private function delay(int $ms): void
    {
        if ($this->lastRequestAt) {
            usleep((int) max(0, $ms * 1000 - (microtime(true) - $this->lastRequestAt) * 1000000));
        } $this->lastRequestAt = microtime(true);
    }

    private function searchText(string $text): string
    {
        $normalized = $this->normalizer->normalize($text);

        return mb_substr($normalized, 0, 500);
    }

    private function emptyDorar(): array
    {
        return ['rawi' => null, 'mohdith' => null, 'mohdithId' => null, 'book' => null, 'bookId' => null, 'numberOrPage' => null, 'grade' => null, 'explainGrade' => null, 'takhrij' => null, 'hadithId' => null, 'url' => null, 'sharh' => null, 'source' => 'Dorar'];
    }

    private function result(bool $matched, float $confidence, bool $review, ?string $type, ?string $message, ?array $dorar = null, bool $cached = false): array
    {
        return ['matched' => $matched, 'confidence' => $confidence, 'needs_review' => $review, 'error_type' => $type, 'error_message' => $message, 'dorar' => $dorar ?? $this->emptyDorar(), 'from_cache' => $cached];
    }
}

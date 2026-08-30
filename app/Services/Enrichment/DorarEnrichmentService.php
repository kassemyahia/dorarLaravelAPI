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

    private HadithSearchQueryExtractor $queryExtractor;

    public function __construct(private readonly HadithSearchService $search, private readonly ArabicNormalizer $normalizer, private readonly HadithMatcherService $matcher, ?SharhSearchService $sharh = null, ?DorarBookMap $books = null, ?HadithSearchQueryExtractor $queryExtractor = null)
    {
        $this->sharh = $sharh ?? app(SharhSearchService::class);
        $this->books = $books ?? new DorarBookMap;
        $this->queryExtractor = $queryExtractor ?? new HadithSearchQueryExtractor;
    }

    public function enrichHadith(string $hadithText, ?string $bookId = null, int $delayMs = 5000, float $lowThreshold = .8, float $highThreshold = .95, ?string $bookName = null, int|string|null $number = null): array
    {
        $normalized = $this->normalizer->normalizeAndHash($hadithText);
        if ($cached = HadithNormalizedCache::where('normalized_hash', $normalized['hash'])->where('matched', true)->first()) {
            return $this->result($cached->matched, $cached->confidence, $cached->needs_review, null, null, $cached->dorar_result, true);
        }
        try {
            $queries = $this->queryExtractor->extract(
                $hadithText,
                (int) config('dorar.enrichment_search_attempts', 2),
            );
            $selection = null;
            $queryUsed = $queries[0] ?? '';
            $searchDiagnostics = [];

            foreach ($queries as $query) {
                $this->delay($delayMs);
                $params = ['value' => $query, 'st' => 'w'];
                if ($source = $this->books->verifiedDorarSourceId($bookId)) {
                    $params['s'] = [$source];
                }
                $response = $this->search->searchUsingSiteDorar($params, 'home', true, false);
                $candidates = $response['data'] ?? [];
                if ($candidates === []) {
                    $searchDiagnostics[] = ['query' => $query, 'candidateCount' => 0];

                    continue;
                }

                $candidateSelection = $this->matcher->bestCandidate($query, $candidates, ['book_aliases' => $this->books->aliases($bookId, $bookName), 'number' => $number]);
                $candidateScore = $candidateSelection['best']['score'] ?? 0;
                $searchDiagnostics[] = ['query' => $query, 'candidateCount' => count($candidates), 'bestScore' => $candidateScore];

                if ($selection === null || $candidateScore > ($selection['best']['score'] ?? 0)) {
                    $selection = $candidateSelection;
                    $queryUsed = $query;
                }
                if ($candidateScore >= $lowThreshold) {
                    break;
                }
            }

            if ($selection === null) {
                $result = $this->result(false, 0, false, 'NOT_FOUND', 'No Dorar results found');
                $result['diagnostics'] = ['searchQueries' => $queries, 'searchAttempts' => $searchDiagnostics];

                return $result;
            }
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
            $result['diagnostics'] = [
                'searchQueries' => $queries,
                'selectedQuery' => $queryUsed,
                'searchAttempts' => $searchDiagnostics,
                'candidates' => $selection['diagnostics'],
            ];
            if ($matched) {
                HadithNormalizedCache::updateOrCreate(['normalized_hash' => $normalized['hash']], ['original_text' => $hadithText, 'dorar_result' => $dorar, 'matched' => true, 'confidence' => $confidence, 'needs_review' => $review, 'error_type' => null]);
            }

            return $result;
        } catch (ApiException $e) {
            $type = match (true) {
                $e->getCode() === 403 => 'REQUEST_FAILED', $e->getCode() === 429 => 'RATE_LIMITED', str_contains(strtolower($e->getMessage()), 'timeout') => 'TIMEOUT', str_contains(strtolower($e->getMessage()), 'structure'), str_contains(strtolower($e->getMessage()), 'parsing') => 'PARSING_FAILED', default => 'REQUEST_FAILED'
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

    private function emptyDorar(): array
    {
        return ['rawi' => null, 'mohdith' => null, 'mohdithId' => null, 'book' => null, 'bookId' => null, 'numberOrPage' => null, 'grade' => null, 'explainGrade' => null, 'takhrij' => null, 'hadithId' => null, 'url' => null, 'sharh' => null, 'source' => 'Dorar'];
    }

    private function result(bool $matched, float $confidence, bool $review, ?string $type, ?string $message, ?array $dorar = null, bool $cached = false): array
    {
        return ['matched' => $matched, 'confidence' => $confidence, 'needs_review' => $review, 'error_type' => $type, 'error_message' => $message, 'dorar' => $dorar ?? $this->emptyDorar(), 'from_cache' => $cached];
    }
}

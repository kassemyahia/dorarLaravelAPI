<?php

namespace App\Services\Enrichment;

use App\Exceptions\ApiException;
use App\Models\HadithNormalizedCache;
use App\Services\Dorar\HadithSearchService;
use Illuminate\Support\Facades\Log;

/**
 * Queries Dorar and enriches hadith records.
 * Handles caching, rate limiting, and error handling.
 */
class DorarEnrichmentService
{
    private const ERROR_NOT_FOUND = 'NOT_FOUND';

    private const ERROR_REQUEST_FAILED = 'REQUEST_FAILED';

    private const ERROR_RATE_LIMITED = 'RATE_LIMITED';

    private const ERROR_PARSING_FAILED = 'PARSING_FAILED';

    private const ERROR_TIMEOUT = 'TIMEOUT';

    private const ERROR_UNKNOWN = 'UNKNOWN';

    private int $lastRequestTime = 0;

    public function __construct(
        private readonly HadithSearchService $hadithSearchService,
        private readonly ArabicNormalizer $normalizer,
        private readonly HadithMatcherService $matcherService,
    ) {}

    /**
     * Enrich a single hadith text by searching Dorar.
     * Uses cache to avoid duplicate requests.
     *
     * Returns:
     * [
     *   'matched' => bool,
     *   'confidence' => float|null,
     *   'needs_review' => bool,
     *   'error_type' => string|null,
     *   'error_message' => string|null,
     *   'dorar' => [ ... ] | null,
     * ]
     */
    public function enrichHadith(
        string $hadithText,
        ?string $bookId = null,
        int $delayMs = 5000,
    ): array {
        // Normalize and hash
        $normalized = $this->normalizer->normalizeAndHash($hadithText);

        // Check cache first
        $cached = HadithNormalizedCache::where('normalized_hash', $normalized['hash'])->first();
        if ($cached) {
            Log::info('Hadith enrichment cache hit', [
                'hash' => $normalized['hash'],
                'matched' => $cached->matched,
            ]);

            return [
                'matched' => $cached->matched,
                'confidence' => $cached->confidence,
                'needs_review' => $cached->needs_review,
                'error_type' => $cached->error_type,
                'error_message' => null,
                'dorar' => $cached->dorar_result,
                'from_cache' => true,
            ];
        }

        // Rate limiting: ensure delay between requests
        $this->enforceDelay($delayMs);

        // Query Dorar
        try {
            $result = $this->queryDorar($normalized['normalized'], $bookId);
            $this->lastRequestTime = time();

            // Evaluate best match
            if (! empty($result['matches'])) {
                $match = $result['matches'][0];

                $confidence = $this->matcherService->calculateConfidence(
                    $hadithText,
                    $match['hadith'] ?? null,
                    $bookId,
                    $match['bookId'] ?? null,
                    $match['rawi'] ?? null,
                );

                $needsReview = $this->matcherService->shouldMarkForReview($confidence);
                $matched = $confidence >= 0.80; // Accept medium+ confidence

                $dorarData = [
                    'rawi' => $match['rawi'] ?? null,
                    'mohdith' => $match['mohdith'] ?? null,
                    'mohdithId' => $match['mohdithId'] ?? null,
                    'book' => $match['book'] ?? null,
                    'bookId' => $match['bookId'] ?? null,
                    'numberOrPage' => $match['numberOrPage'] ?? null,
                    'grade' => $match['grade'] ?? null,
                    'explainGrade' => $match['explainGrade'] ?? null,
                    'takhrij' => $match['takhrij'] ?? null,
                    'hadithId' => $match['hadithId'] ?? null,
                    'url' => $match['url'] ?? null,
                    'source' => 'Dorar',
                ];

                // Cache result
                HadithNormalizedCache::create([
                    'normalized_hash' => $normalized['hash'],
                    'original_text' => $hadithText,
                    'dorar_result' => $dorarData,
                    'matched' => $matched,
                    'confidence' => $confidence,
                    'needs_review' => $needsReview,
                    'error_type' => null,
                ]);

                return [
                    'matched' => $matched,
                    'confidence' => $confidence,
                    'needs_review' => $needsReview,
                    'error_type' => null,
                    'error_message' => null,
                    'dorar' => $dorarData,
                    'from_cache' => false,
                ];
            }

            // No matches found
            HadithNormalizedCache::create([
                'normalized_hash' => $normalized['hash'],
                'original_text' => $hadithText,
                'dorar_result' => null,
                'matched' => false,
                'confidence' => 0.0,
                'needs_review' => false,
                'error_type' => self::ERROR_NOT_FOUND,
            ]);

            return [
                'matched' => false,
                'confidence' => 0.0,
                'needs_review' => false,
                'error_type' => self::ERROR_NOT_FOUND,
                'error_message' => 'No matching hadith found in Dorar',
                'dorar' => null,
                'from_cache' => false,
            ];
        } catch (ApiException $e) {
            $this->lastRequestTime = time();

            $errorType = $this->mapApiExceptionToErrorType($e);
            $errorMessage = $e->getMessage();

            Log::warning('Dorar enrichment failed', [
                'error_type' => $errorType,
                'error_message' => $errorMessage,
                'hadith_preview' => mb_substr($hadithText, 0, 50),
            ]);

            // Cache error for future reference
            HadithNormalizedCache::create([
                'normalized_hash' => $normalized['hash'],
                'original_text' => $hadithText,
                'dorar_result' => null,
                'matched' => false,
                'confidence' => 0.0,
                'needs_review' => false,
                'error_type' => $errorType,
            ]);

            return [
                'matched' => false,
                'confidence' => 0.0,
                'needs_review' => false,
                'error_type' => $errorType,
                'error_message' => $errorMessage,
                'dorar' => null,
                'from_cache' => false,
            ];
        } catch (\Exception $e) {
            $this->lastRequestTime = time();

            Log::error('Unexpected error in hadith enrichment', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'hadith_preview' => mb_substr($hadithText, 0, 50),
            ]);

            HadithNormalizedCache::create([
                'normalized_hash' => $normalized['hash'],
                'original_text' => $hadithText,
                'dorar_result' => null,
                'matched' => false,
                'confidence' => 0.0,
                'needs_review' => false,
                'error_type' => self::ERROR_UNKNOWN,
            ]);

            return [
                'matched' => false,
                'confidence' => 0.0,
                'needs_review' => false,
                'error_type' => self::ERROR_UNKNOWN,
                'error_message' => 'Unexpected error during enrichment',
                'dorar' => null,
                'from_cache' => false,
            ];
        }
    }

    /**
     * Query Dorar with rate limiting.
     */
    private function queryDorar(string $normalizedText, ?string $bookId = null): array
    {
        $queryParams = ['value' => $normalizedText];

        if ($bookId) {
            $queryParams['site'] = $bookId;
        }

        $result = $this->hadithSearchService->searchUsingSiteDorar(
            queryParams: $queryParams,
            tab: 'home',
            isRemoveHTML: true,
            isForSpecialist: false,
        );

        return [
            'matches' => $result['data'] ?? [],
        ];
    }

    /**
     * Enforce delay between Dorar requests.
     * Adds jitter (+/- 1 second).
     */
    private function enforceDelay(int $delayMs): void
    {
        $now = microtime(true);
        $lastRequestTime = $this->lastRequestTime ?: ($now - 1000);
        $elapsedMs = ($now - $lastRequestTime) * 1000;

        $jitterMs = rand(-1000, 1000); // +/- 1 second
        $targetDelayMs = $delayMs + $jitterMs;

        if ($elapsedMs < $targetDelayMs) {
            $sleepMs = (int) ($targetDelayMs - $elapsedMs);
            usleep($sleepMs * 1000);
        }
    }

    /**
     * Map API exceptions to error types.
     */
    private function mapApiExceptionToErrorType(ApiException $e): string
    {
        $status = $e->getCode();

        if ($status === 429) {
            return self::ERROR_RATE_LIMITED;
        }

        if (in_array($status, [500, 502, 503, 504, 520, 521, 522, 523, 524, 525, 526, 530], true)) {
            return self::ERROR_REQUEST_FAILED;
        }

        if (str_contains($e->getMessage(), 'timeout')) {
            return self::ERROR_TIMEOUT;
        }

        if (str_contains($e->getMessage(), 'parsing')) {
            return self::ERROR_PARSING_FAILED;
        }

        return self::ERROR_UNKNOWN;
    }
}

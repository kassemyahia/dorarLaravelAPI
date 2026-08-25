<?php

namespace App\Services\Enrichment;

/**
 * Calculates confidence scores for hadith matches.
 * Evaluates Dorar results against the query.
 */
class HadithMatcherService
{
    private float $highConfidenceThreshold = 0.95;

    private float $mediumConfidenceThreshold = 0.80;

    public function __construct(private readonly ArabicNormalizer $normalizer) {}

    public function setThresholds(float $high, float $medium): void
    {
        $this->highConfidenceThreshold = $high;
        $this->mediumConfidenceThreshold = $medium;
    }

    /**
     * Calculate confidence score for a potential match.
     *
     * Scoring logic:
     * - Text similarity: Use normalized text comparison
     * - Book ID match: Strong signal (when available)
     * - Rawi (narrator) match: Medium signal
     *
     * Returns confidence score 0-1.
     */
    public function calculateConfidence(
        string $queryText,
        ?string $dorarText = null,
        ?string $queryBookId = null,
        ?string $dorarBookId = null,
        ?string $dorarRawi = null,
    ): float {
        if (! $dorarText) {
            return 0.0;
        }

        $queryNorm = $this->normalizer->normalize($queryText);
        $dorarNorm = $this->normalizer->normalize($dorarText);

        // If texts are identical after normalization, high confidence
        if ($queryNorm === $dorarNorm) {
            $confidence = 0.98;
        } else {
            // Use Levenshtein distance for similarity
            $similarity = $this->calculateTextSimilarity($queryNorm, $dorarNorm);
            $confidence = $similarity;
        }

        // Boost confidence if book ID matches
        if ($queryBookId && $dorarBookId && (string) $queryBookId === (string) $dorarBookId) {
            $confidence = min(1.0, $confidence + 0.05);
        }

        // Slight boost for having narrator info
        if ($dorarRawi && trim($dorarRawi) !== '') {
            $confidence = min(1.0, $confidence + 0.02);
        }

        return round($confidence, 4);
    }

    /**
     * Calculate text similarity using normalized Levenshtein distance.
     * Returns 0.0 to 1.0, where 1.0 is identical.
     */
    private function calculateTextSimilarity(string $text1, string $text2): float
    {
        $len1 = mb_strlen($text1);
        $len2 = mb_strlen($text2);

        if ($len1 === 0 && $len2 === 0) {
            return 1.0;
        }

        if ($len1 === 0 || $len2 === 0) {
            return 0.0;
        }

        $distance = levenshtein($text1, $text2);
        $maxLen = max($len1, $len2);

        return 1.0 - ($distance / $maxLen);
    }

    /**
     * Classify confidence level.
     */
    public function getConfidenceLevel(float $confidence): string
    {
        if ($confidence >= $this->highConfidenceThreshold) {
            return 'high';
        }

        if ($confidence >= $this->mediumConfidenceThreshold) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Determine if match should be marked for review.
     */
    public function shouldMarkForReview(float $confidence): bool
    {
        return $confidence < $this->mediumConfidenceThreshold;
    }

    /**
     * Evaluate if a match is acceptable.
     * Low confidence matches are flagged for review.
     */
    public function isAcceptableMatch(float $confidence): bool
    {
        return ! $this->shouldMarkForReview($confidence);
    }
}

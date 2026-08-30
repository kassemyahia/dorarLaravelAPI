<?php

namespace App\Services\Enrichment;

class HadithMatcherService
{
    private float $high = .95;

    private float $medium = .8;

    public function __construct(private readonly ArabicNormalizer $normalizer) {}

    public function calculateConfidence(
        string $queryText,
        ?string $dorarText = null,
        ?string $queryBookId = null,
        ?string $dorarBookId = null,
        ?string $dorarRawi = null,
        array $bookAliases = [],
        ?string $dorarBook = null,
        int|string|null $number = null,
        int|string|null $dorarNumber = null,
    ): float {
        if (! $dorarText) {
            return 0.0;
        }
        $a = $this->normalizer->normalize($queryText);
        $b = $this->normalizer->normalize($dorarText);
        if ($a === $b) {
            return ($queryBookId && $dorarBookId && $queryBookId === $dorarBookId) ? 1.0 : .98;
        }

        $ta = array_unique($this->normalizer->tokens($a));
        $tb = array_unique($this->normalizer->tokens($b));
        $union = array_unique([...$ta, ...$tb]);
        $intersection = count(array_intersect($ta, $tb));
        $jaccard = count($union) ? $intersection / count($union) : 0;
        $shorterCount = min(count($ta), count($tb));
        $coverage = $shorterCount ? $intersection / $shorterCount : 0;
        $coverageScore = $shorterCount >= 4 ? .9 * $coverage + .1 * $jaccard : 0;
        $containment = min(mb_strlen($a), mb_strlen($b)) > 20 && (str_contains($a, $b) || str_contains($b, $a)) ? .95 : 0;
        $score = max($jaccard, $coverageScore, $containment);
        if ($dorarBook && collect($bookAliases)->contains(fn ($alias) => str_contains($this->normalizer->normalize($dorarBook), $this->normalizer->normalize($alias)))) {
            $score += .03;
        }
        if ($number !== null && $dorarNumber !== null && preg_replace('/\D/u', '', (string) $number) === preg_replace('/\D/u', '', (string) $dorarNumber)) {
            $score += .02;
        }

        return round(min(1, $score), 4);
    }

    public function bestCandidate(string $text, array $candidates, array $signals = []): array
    {
        $scored = [];
        foreach ($candidates as $index => $candidate) {
            $score = $this->calculateConfidence($text, $candidate['hadith'] ?? null,
                bookAliases: $signals['book_aliases'] ?? [], dorarBook: $candidate['book'] ?? null,
                number: $signals['number'] ?? null, dorarNumber: $candidate['numberOrPage'] ?? null);
            $scored[] = ['index' => $index, 'score' => $score, 'candidate' => $candidate];
        }
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return ['best' => $scored[0] ?? null, 'diagnostics' => array_map(fn ($x) => ['index' => $x['index'], 'score' => $x['score'], 'hadithId' => $x['candidate']['hadithId'] ?? null], array_slice($scored, 0, 5))];
    }

    public function setThresholds(float $high, float $medium): void
    {
        $this->high = $high;
        $this->medium = $medium;
    }

    public function getConfidenceLevel(float $confidence): string
    {
        return $confidence >= $this->high ? 'high' : ($confidence >= $this->medium ? 'medium' : 'low');
    }

    public function shouldMarkForReview(float $confidence): bool
    {
        return $confidence < $this->medium;
    }

    public function isAcceptableMatch(float $confidence): bool
    {
        return $confidence >= $this->medium;
    }
}

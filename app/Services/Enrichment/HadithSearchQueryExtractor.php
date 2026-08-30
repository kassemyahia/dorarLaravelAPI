<?php

namespace App\Services\Enrichment;

class HadithSearchQueryExtractor
{
    private const MAX_WORDS = 8;

    private const MIN_WORDS = 4;

    private const ISNAD_WORDS = [
        'حدثنا', 'حدثني', 'أخبرنا', 'أخبرني', 'انبأنا', 'أنبأنا', 'قال', 'فقال',
        'سمعت', 'يقول', 'عن', 'رضي', 'الله', 'عنه', 'عنها', 'عنهما', 'عليهم',
    ];

    /**
     * Build short matn-oriented phrases suitable for Dorar's all-words search.
     *
     * The nine-books JSON stores the full isnad before the hadith text. Sending
     * the first hundreds of characters therefore searches for narrator chains,
     * which are much less stable than the matn indexed by Dorar.
     *
     * @return array<int, string>
     */
    public function extract(string $text, int $limit = 2): array
    {
        $limit = min(3, max(1, $limit));
        $visible = $this->removeInvisibleAndMarks($text);
        $phrases = [];

        preg_match_all('/["“”«»]([^"“”«»]+)["“”«»]/u', $visible, $quoted);
        foreach ($quoted[1] ?? [] as $segment) {
            $this->addPhrase($phrases, $segment);
            if (count($phrases) < $limit) {
                $this->addShiftedPhrase($phrases, $segment);
            }
            if (count($phrases) >= $limit) {
                break;
            }
        }

        $searchable = $this->clean($visible);
        $markerPatterns = [
            '/(?:سمعت رسول الله|قال رسول الله|قال النبي)(?: صلى الله عليه وسلم)?\s+(.+)$/u',
            '/(?:عن النبي|أن رسول الله)(?: صلى الله عليه وسلم)?\s+(?:قال|يقول)?\s*(.+)$/u',
        ];
        foreach ($markerPatterns as $pattern) {
            if (count($phrases) >= $limit) {
                break;
            }
            if (preg_match($pattern, $searchable, $match) === 1) {
                $this->addPhrase($phrases, $match[1]);
            }
        }

        foreach ($this->rankedSegments($visible) as $segment) {
            if (count($phrases) >= $limit) {
                break;
            }
            $this->addPhrase($phrases, $segment);
        }

        if ($phrases === []) {
            $words = preg_split('/\s+/u', $searchable) ?: [];
            if ($words !== []) {
                $phrase = implode(' ', array_slice($words, 0, self::MAX_WORDS));
                $phrases[mb_strtolower($phrase)] = $phrase;
            }
        }

        return array_slice(array_values($phrases), 0, $limit);
    }

    private function addShiftedPhrase(array &$phrases, string $text): void
    {
        $words = preg_split('/\s+/u', $this->clean($text)) ?: [];
        if (count($words) <= self::MAX_WORDS) {
            return;
        }

        $this->addPhrase($phrases, implode(' ', array_slice($words, 3)));
    }

    private function addPhrase(array &$phrases, string $text): void
    {
        $words = preg_split('/\s+/u', $this->clean($text)) ?: [];
        if (count($words) < self::MIN_WORDS) {
            return;
        }

        $phrase = implode(' ', array_slice($words, 0, self::MAX_WORDS));
        $key = mb_strtolower($phrase);
        $phrases[$key] ??= $phrase;
    }

    /** @return array<int, string> */
    private function rankedSegments(string $text): array
    {
        $segments = preg_split('/[\n\r،,:؛.!؟]+/u', $text) ?: [];
        $scored = [];

        foreach ($segments as $index => $segment) {
            $words = preg_split('/\s+/u', $this->clean($segment)) ?: [];
            if (count($words) < self::MIN_WORDS) {
                continue;
            }

            $isnadCount = count(array_filter(
                $words,
                fn (string $word): bool => in_array($word, self::ISNAD_WORDS, true),
            ));
            $contentCount = count($words) - $isnadCount;
            $scored[] = [
                'text' => $segment,
                'score' => $contentCount - ($isnadCount * 2) + min(count($words), self::MAX_WORDS) / 100 + $index / 10000,
            ];
        }

        usort($scored, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_column($scored, 'text');
    }

    private function removeInvisibleAndMarks(string $text): string
    {
        return preg_replace('/[\p{Cf}\p{M}\x{0640}]+/u', '', $text) ?? $text;
    }

    private function clean(string $text): string
    {
        $text = $this->removeInvisibleAndMarks($text);
        $text = preg_replace('/[\p{P}\p{S}]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}

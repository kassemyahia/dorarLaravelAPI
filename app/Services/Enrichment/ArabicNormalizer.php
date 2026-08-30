<?php

namespace App\Services\Enrichment;

/**
 * Arabic text normalization for matching purposes.
 * Handles diacritics, tatweel, punctuation, and common variations.
 */
class ArabicNormalizer
{
    /**
     * Map of Arabic letters with diacritics to their base forms.
     */
    private const DIACRITIC_MAP = [
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا',  // alef with hamza/madda
        'ة' => 'ه',                              // ta marbuta to ha
        'ؤ' => 'و',                              // waw with hamza
        'ئ' => 'ي',                              // ya with hamza
        'ّ' => '',                               // shadda
        'َ' => '',                               // fatha
        'ِ' => '',                               // kasra
        'ُ' => '',                               // damma
        'ً' => '',                               // double fatha
        'ٌ' => '',                               // double damma
        'ٍ' => '',                               // double kasra
        'ْ' => '',                               // sukun
        'ـ' => '',                               // tatweel (kashida)
        'ى' => 'ي',
    ];

    /**
     * Punctuation and symbols to remove.
     */
    private const PUNCTUATION_PATTERN = '/[\p{P}\p{S}—–-]+/u';

    /**
     * Normalize Arabic text for matching.
     *
     * Steps:
     * 1. Remove diacritics
     * 2. Remove tatweel
     * 3. Normalize alef variations
     * 4. Remove punctuation
     * 5. Collapse whitespace
     * 6. Trim
     * 7. Convert to lowercase (for consistency)
     */
    public function normalize(string $text): string
    {
        $text = (string) $text;

        // The nine-books files contain right-to-left marks and other invisible
        // format characters that should never affect hashes or comparisons.
        $text = preg_replace('/\p{Cf}+/u', '', $text) ?? $text;

        // Remove diacritics and tatweel
        $text = strtr($text, self::DIACRITIC_MAP);

        // Remove punctuation and symbols
        $text = preg_replace(self::PUNCTUATION_PATTERN, ' ', $text);

        // Normalize multiple spaces to single space
        $text = preg_replace('/\s+/u', ' ', $text);

        // Trim
        $text = trim($text);

        return $text;
    }

    /**
     * Calculate SHA-256 hash of normalized text.
     * Used for cache lookups to avoid duplicate Dorar requests.
     */
    public function hashNormalizedText(string $text): string
    {
        $normalized = $this->normalize($text);

        return hash('sha256', $normalized);
    }

    /**
     * Get normalized form and its hash.
     */
    public function normalizeAndHash(string $text): array
    {
        $normalized = $this->normalize($text);
        $hash = hash('sha256', $normalized);

        return [
            'original' => $text,
            'normalized' => $normalized,
            'hash' => $hash,
        ];
    }

    public function tokens(string $text): array
    {
        return array_values(array_filter(preg_split('/\s+/u', $this->normalize($text)) ?: []));
    }
}

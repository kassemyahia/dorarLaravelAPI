<?php

namespace Tests\Unit\Enrichment;

use App\Services\Enrichment\ArabicNormalizer;
use PHPUnit\Framework\TestCase;

class ArabicNormalizerTest extends TestCase
{
    private ArabicNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new ArabicNormalizer;
    }

    /**
     * Test basic normalization removes diacritics.
     */
    public function test_normalizes_diacritics(): void
    {
        $text = 'إِنَّمَا الأَعْمَالُ بِالنِّيَّاتِ';
        $normalized = $this->normalizer->normalize($text);

        $this->assertStringNotContainsString('َ', $normalized);
        $this->assertStringNotContainsString('ِ', $normalized);
        $this->assertStringNotContainsString('ُ', $normalized);
        $this->assertStringNotContainsString('ّ', $normalized);
    }

    /**
     * Test normalization removes tatweel (kashida).
     */
    public function test_normalizes_tatweel(): void
    {
        $text = 'الـــــقرآن الـــــكريم';
        $normalized = $this->normalizer->normalize($text);

        $this->assertStringNotContainsString('ـ', $normalized);
    }

    /**
     * Test normalization normalizes alef variations.
     */
    public function test_normalizes_alef_variations(): void
    {
        $texts = [
            'أحمد',
            'إحمد',
            'آحمد',
        ];

        $normalized = array_map(fn ($t) => $this->normalizer->normalize($t), $texts);

        // All should normalize to same base
        $this->assertEquals($normalized[0], $normalized[1]);
        $this->assertEquals($normalized[1], $normalized[2]);
    }

    /**
     * Test normalization collapses whitespace.
     */
    public function test_collapses_whitespace(): void
    {
        $text = 'السلام     عليكم     ورحمة    الله';
        $normalized = $this->normalizer->normalize($text);

        // Check that multiple spaces were collapsed to single space
        $this->assertStringNotContainsString('     ', $normalized);
        $this->assertStringNotContainsString('    ', $normalized);
        // Verify the normalized form contains the words
        $this->assertStringContainsString('السلام', $normalized);
        $this->assertStringContainsString('عليكم', $normalized);
    }

    /**
     * Test hash is deterministic.
     */
    public function test_hash_is_deterministic(): void
    {
        $text = 'إِنَّمَا الأَعْمَالُ بِالنِّيَّاتِ';

        $hash1 = $this->normalizer->hashNormalizedText($text);
        $hash2 = $this->normalizer->hashNormalizedText($text);

        $this->assertEquals($hash1, $hash2);
    }

    /**
     * Test normalizeAndHash returns correct structure.
     */
    public function test_normalize_and_hash_structure(): void
    {
        $text = 'إِنَّمَا الأَعْمَالُ بِالنِّيَّاتِ';
        $result = $this->normalizer->normalizeAndHash($text);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('original', $result);
        $this->assertArrayHasKey('normalized', $result);
        $this->assertArrayHasKey('hash', $result);
        $this->assertEquals($text, $result['original']);
        $this->assertEquals(64, strlen($result['hash'])); // SHA-256 is 64 hex chars
    }

    /**
     * Test empty string handling.
     */
    public function test_handles_empty_string(): void
    {
        $normalized = $this->normalizer->normalize('');
        $this->assertEquals('', $normalized);
    }

    /**
     * Test whitespace-only string handling.
     */
    public function test_handles_whitespace_only(): void
    {
        $normalized = $this->normalizer->normalize('   ');
        $this->assertEquals('', $normalized);
    }
}

<?php

namespace Tests\Unit\Enrichment;

use App\Services\Enrichment\ArabicNormalizer;
use App\Services\Enrichment\HadithMatcherService;
use PHPUnit\Framework\TestCase;

class HadithMatcherServiceTest extends TestCase
{
    private HadithMatcherService $matcher;

    private ArabicNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new ArabicNormalizer;
        $this->matcher = new HadithMatcherService($this->normalizer);
    }

    /**
     * Test identical texts get high confidence.
     */
    public function test_identical_texts_high_confidence(): void
    {
        $text = 'إِنَّمَا الأَعْمَالُ بِالنِّيَّاتِ';
        $confidence = $this->matcher->calculateConfidence(
            queryText: $text,
            dorarText: $text,
        );

        $this->assertGreaterThanOrEqual(0.95, $confidence);
    }

    /**
     * Test identical normalized texts get high confidence.
     */
    public function test_normalized_identical_texts_high_confidence(): void
    {
        $text1 = 'إِنَّمَا الأَعْمَالُ بِالنِّيَّاتِ';
        $text2 = 'انما الاعمال بالنيات';

        $confidence = $this->matcher->calculateConfidence(
            queryText: $text1,
            dorarText: $text2,
        );

        $this->assertGreaterThanOrEqual(0.95, $confidence);
    }

    /**
     * Test book ID match boosts confidence.
     */
    public function test_book_id_match_boosts_confidence(): void
    {
        $queryText = 'الحديث الأول';
        $dorarText = 'الحديث الأول';

        $confidenceWithoutBookId = $this->matcher->calculateConfidence(
            queryText: $queryText,
            dorarText: $dorarText,
        );

        $confidenceWithBookId = $this->matcher->calculateConfidence(
            queryText: $queryText,
            dorarText: $dorarText,
            queryBookId: '123',
            dorarBookId: '123',
        );

        $this->assertGreaterThan($confidenceWithoutBookId, $confidenceWithBookId);
    }

    /**
     * Test different texts get lower confidence.
     */
    public function test_different_texts_lower_confidence(): void
    {
        $confidence = $this->matcher->calculateConfidence(
            queryText: 'النص الأول',
            dorarText: 'النص الثاني المختلف جدا',
        );

        $this->assertLessThan(0.90, $confidence);
    }

    public function test_matn_phrase_matches_a_longer_dorar_text_with_minor_word_order_changes(): void
    {
        $confidence = $this->matcher->calculateConfidence(
            queryText: 'إنما الأعمال بالنيات وإنما لكل امرئ ما نوى',
            dorarText: 'الأعمال إنما تكون بالنيات وإنما لكل امرئ ما نوى فمن كانت هجرته إلى الله ورسوله',
        );

        $this->assertGreaterThanOrEqual(0.80, $confidence);
    }

    /**
     * Test null dorar text returns 0 confidence.
     */
    public function test_null_dorar_text_zero_confidence(): void
    {
        $confidence = $this->matcher->calculateConfidence(
            queryText: 'النص',
            dorarText: null,
        );

        $this->assertEquals(0.0, $confidence);
    }

    /**
     * Test confidence level classification.
     */
    public function test_confidence_level_classification(): void
    {
        $this->assertEquals('high', $this->matcher->getConfidenceLevel(0.96));
        $this->assertEquals('medium', $this->matcher->getConfidenceLevel(0.85));
        $this->assertEquals('low', $this->matcher->getConfidenceLevel(0.70));
    }

    /**
     * Test mark for review threshold.
     */
    public function test_should_mark_for_review(): void
    {
        $this->assertTrue($this->matcher->shouldMarkForReview(0.75));
        $this->assertFalse($this->matcher->shouldMarkForReview(0.85));
    }

    /**
     * Test acceptable match threshold.
     */
    public function test_is_acceptable_match(): void
    {
        $this->assertTrue($this->matcher->isAcceptableMatch(0.85));
        $this->assertFalse($this->matcher->isAcceptableMatch(0.75));
    }

    /**
     * Test custom thresholds.
     */
    public function test_custom_thresholds(): void
    {
        $this->matcher->setThresholds(0.90, 0.70);

        $this->assertEquals('high', $this->matcher->getConfidenceLevel(0.91));
        $this->assertEquals('medium', $this->matcher->getConfidenceLevel(0.75));
        $this->assertEquals('low', $this->matcher->getConfidenceLevel(0.60));
    }
}

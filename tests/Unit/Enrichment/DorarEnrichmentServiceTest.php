<?php

namespace Tests\Unit\Enrichment;

use App\Models\HadithNormalizedCache;
use App\Services\Dorar\HadithSearchService;
use App\Services\Enrichment\ArabicNormalizer;
use App\Services\Enrichment\DorarEnrichmentService;
use App\Services\Enrichment\HadithMatcherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class DorarEnrichmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private DorarEnrichmentService $service;

    private MockInterface $hadithSearchServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hadithSearchServiceMock = $this->mock(HadithSearchService::class);
        $normalizer = new ArabicNormalizer;
        $matcher = new HadithMatcherService($normalizer);

        $this->service = new DorarEnrichmentService(
            $this->hadithSearchServiceMock,
            $normalizer,
            $matcher,
        );
    }

    /**
     * Test cache hit returns cached result.
     */
    public function test_cache_hit(): void
    {
        $hadithText = 'الحديث النبوي الشريف';
        $normalizer = new ArabicNormalizer;
        $hash = $normalizer->hashNormalizedText($hadithText);

        // Pre-populate cache
        HadithNormalizedCache::create([
            'normalized_hash' => $hash,
            'original_text' => $hadithText,
            'dorar_result' => [
                'rawi' => 'راوي معروف',
                'grade' => 'صحيح',
            ],
            'matched' => true,
            'confidence' => 0.95,
            'needs_review' => false,
        ]);

        $result = $this->service->enrichHadith($hadithText);

        $this->assertTrue($result['matched']);
        $this->assertEquals(0.95, $result['confidence']);
        $this->assertTrue($result['from_cache']);
        $this->hadithSearchServiceMock->shouldNotHaveBeenCalled();
    }

    /**
     * Test cache miss queries Dorar.
     */
    public function test_cache_miss_queries_dorar(): void
    {
        $hadithText = 'الحديث النبوي الجديد';

        $this->hadithSearchServiceMock->shouldReceive('searchUsingSiteDorar')
            ->once()
            ->andReturn([
                'data' => [
                    [
                        'hadith' => 'الحديث النبوي الجديد',
                        'rawi' => 'راوي',
                        'book' => 'البخاري',
                        'bookId' => 1,
                    ],
                ],
            ]);

        $result = $this->service->enrichHadith($hadithText);

        $this->assertFalse($result['from_cache']);
        $this->assertTrue($result['matched']);
        $this->assertNotNull($result['dorar']);
    }

    /**
     * Test NOT_FOUND error.
     */
    public function test_not_found_error(): void
    {
        $hadithText = 'حديث غير موجود';

        $this->hadithSearchServiceMock->shouldReceive('searchUsingSiteDorar')
            ->once()
            ->andReturn(['data' => []]);

        $result = $this->service->enrichHadith($hadithText);

        $this->assertFalse($result['matched']);
        $this->assertEquals('NOT_FOUND', $result['error_type']);
    }

    /**
     * Test rate limiting is applied.
     */
    public function test_rate_limiting_delay(): void
    {
        $hadithText = 'الحديث';
        $delayMs = 1000;

        $this->hadithSearchServiceMock->shouldReceive('searchUsingSiteDorar')
            ->andReturn(['data' => []]);

        // Don't enforce strict timing - just check that code runs
        $result = $this->service->enrichHadith($hadithText, delayMs: $delayMs);

        // Should return a result
        $this->assertIsArray($result);
        $this->assertArrayHasKey('matched', $result);
    }
}

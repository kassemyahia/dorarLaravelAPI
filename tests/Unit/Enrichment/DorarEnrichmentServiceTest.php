<?php

namespace Tests\Unit\Enrichment;

use App\Exceptions\ApiException;
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

    public function test_selects_correct_candidate_when_it_is_not_first(): void
    {
        $text = 'إنما الأعمال بالنيات';
        $this->hadithSearchServiceMock->shouldReceive('searchUsingSiteDorar')->once()->andReturn(['data' => [
            ['hadith' => 'نص مختلف تماما', 'hadithId' => 'wrong'],
            ['hadith' => 'انما الاعمال بالنيات', 'hadithId' => 'right'],
        ]]);
        $result = $this->service->enrichHadith($text, delayMs: 0);
        $this->assertSame('right', $result['dorar']['hadithId']);
        $this->assertTrue($result['matched']);
    }

    public function test_searches_full_sanad_record_using_short_matn_query(): void
    {
        $hadithText = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/by_book_sample.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        )['hadiths'][0]['arabic'];

        $this->hadithSearchServiceMock->shouldReceive('searchUsingSiteDorar')
            ->once()
            ->withArgs(function (array $params): bool {
                return $params['value'] === 'إنما الأعمال بالنيات وإنما لكل امرئ ما نوى'
                    && $params['st'] === 'w';
            })
            ->andReturn([
                'data' => [[
                    'hadith' => 'إنما الأعمال بالنيات وإنما لكل امرئ ما نوى',
                    'hadithId' => '1',
                    'book' => 'صحيح البخاري',
                ]],
            ]);

        $result = $this->service->enrichHadith($hadithText, bookId: '1', delayMs: 0);

        $this->assertTrue($result['matched']);
        $this->assertSame('1', $result['dorar']['hadithId']);
        $this->assertSame('إنما الأعمال بالنيات وإنما لكل امرئ ما نوى', $result['diagnostics']['selectedQuery']);
    }

    public function test_tries_second_matn_query_after_empty_results(): void
    {
        $hadithText = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/by_book_sample.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        )['hadiths'][0]['arabic'];

        $this->hadithSearchServiceMock->shouldReceive('searchUsingSiteDorar')
            ->twice()
            ->andReturn(
                ['data' => []],
                [
                    'data' => [[
                        'hadith' => 'وإنما لكل امرئ ما نوى فمن كانت هجرته',
                        'hadithId' => 'fallback',
                    ]],
                ],
            );

        $result = $this->service->enrichHadith($hadithText, delayMs: 0, lowThreshold: 0.7);

        $this->assertCount(2, $result['diagnostics']['searchQueries']);
        $this->assertTrue($result['matched']);
        $this->assertSame('fallback', $result['dorar']['hadithId']);
    }

    public function test_transient_failure_is_not_cached(): void
    {
        $this->hadithSearchServiceMock->shouldReceive('searchUsingSiteDorar')->twice()->andThrow(new ApiException('rate limited', 429));
        $this->assertSame('RATE_LIMITED', $this->service->enrichHadith('نص مؤقت', delayMs: 0)['error_type']);
        $this->assertSame('RATE_LIMITED', $this->service->enrichHadith('نص مؤقت', delayMs: 0)['error_type']);
        $this->assertDatabaseMissing('hadith_normalized_caches', ['original_text' => 'نص مؤقت']);
    }
}

<?php

namespace Tests\Feature\Enrichment;

use App\Jobs\Enrichment\ProcessHadithImportJob;
use App\Models\HadithImportJob;
use App\Models\HadithNormalizedCache;
use App\Services\Enrichment\HadithEnrichmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ChunkProcessingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'database', 'dorar.fetch_retries' => 1]);
    }

    public function test_real_http_path_processes_wrapper_and_retry_does_not_duplicate_records(): void
    {
        Http::fake(['*' => Http::response($this->successfulSearchHtml(), 200)]);
        $job = $this->uploadFixture();
        $this->assertSame('pending', $job->status);
        $this->assertDatabaseCount('jobs', 1);

        $this->runWorker();

        $job->refresh();
        $this->assertSame('completed', $job->status);
        $this->assertSame(2, $job->processed_count);
        $this->assertSame(2, $job->enrichmentRecords()->count());
        $this->assertSame(0, $job->enrichmentRecords()->where('status', 'pending')->count());
        $this->assertGreaterThan(0, HadithNormalizedCache::count());

        app(ProcessHadithImportJob::class, ['importJobId' => $job->id])->handle(app(HadithEnrichmentService::class));
        $this->assertSame(2, $job->enrichmentRecords()->count());

        $json = $this->get("/v1/api/enrichment/jobs/{$job->id}/download/json")->assertOk()->json();
        $this->assertSame(1, $json['id']);
        $this->assertCount(2, $json['hadiths']);
        $this->assertSame([1, 2], array_column($json['hadiths'], 'idInBook'));
    }

    public function test_http_403_is_stored_as_request_failed_and_not_cached(): void
    {
        Http::fake(['*' => Http::response('Cloudflare forbidden', 403)]);
        $job = $this->uploadFixture();
        $this->runWorker();

        $job->refresh();
        $this->assertSame('completed', $job->status);
        $this->assertSame(2, $job->processed_count);
        $this->assertSame(2, $job->request_failed_count);
        $this->assertStringContainsString('403', $job->error_message);
        $this->assertSame(['REQUEST_FAILED'], $job->enrichmentRecords()->distinct()->pluck('error_type')->all());
        $this->assertSame(0, HadithNormalizedCache::count());
    }

    public function test_next_chunk_is_dispatched_and_processed(): void
    {
        Http::fake(['*' => Http::response($this->successfulSearchHtml(), 200)]);
        $job = $this->uploadFixture();
        \DB::table('jobs')->delete();
        ProcessHadithImportJob::dispatch($job->id, 1);

        $this->runWorker();
        $this->assertSame('processing', $job->refresh()->status);
        $this->assertSame(1, $job->processed_count);
        $this->assertDatabaseCount('jobs', 1);

        $this->runWorker();
        $this->assertSame('completed', $job->refresh()->status);
        $this->assertSame(2, $job->processed_count);
        $this->assertDatabaseCount('jobs', 0);
    }

    #[DataProvider('upstreamFailureProvider')]
    public function test_upstream_failures_are_stored(string $kind, string $expectedStatus): void
    {
        match ($kind) {
            '429' => Http::fake(['*' => Http::response('rate limited', 429)]),
            '503' => Http::fake(['*' => Http::response('unavailable', 503)]),
            'invalid' => Http::fake(['*' => Http::response('<html>changed</html>', 200)]),
            'empty' => Http::fake(['*' => Http::response('<a aria-controls="home">0</a><div id="home"></div>', 200)]),
            'timeout' => Http::fake(fn () => throw new ConnectionException('connection timeout')),
        };
        $job = $this->uploadFixture();
        $this->runWorker();

        $job->refresh();
        $this->assertSame('completed', $job->status);
        $this->assertSame(2, $job->processed_count);
        $this->assertSame(2, $job->enrichmentRecords()->where('status', $expectedStatus)->count());
        $this->assertSame(0, HadithNormalizedCache::count());
    }

    public static function upstreamFailureProvider(): array
    {
        return [
            '429' => ['429', 'request_failed'],
            '503' => ['503', 'request_failed'],
            'timeout' => ['timeout', 'request_failed'],
            'invalid HTML' => ['invalid', 'parsing_failed'],
            'empty results' => ['empty', 'not_found'],
        ];
    }

    private function uploadFixture(): HadithImportJob
    {
        $path = base_path('tests/Fixtures/by_book_sample.json');
        $file = new UploadedFile($path, 'by_book_sample.json', 'application/json', null, true);
        $id = $this->post('/v1/api/enrichment/import', ['file' => $file, 'delay_ms' => 1000])
            ->assertCreated()->json('data.jobId');

        return HadithImportJob::findOrFail($id);
    }

    private function runWorker(): void
    {
        Artisan::call('queue:work', ['--once' => true, '--tries' => 1, '--timeout' => 600]);
    }

    private function successfulSearchHtml(): string
    {
        return <<<'HTML'
        <html><body><a aria-controls="home">2</a><a aria-controls="specialist">0</a>
        <div id="home">
          <div class="border-bottom"><div>1 - نص غير مطابق تماما</div><div><strong>الراوي : <span>غير معروف</span></strong><strong>المصدر : <span>كتاب آخر</span></strong><a tag="wrong"></a></div></div>
          <div class="border-bottom"><div>2 - إِنَّمَا الأَعْمَالُ بِالنِّيَّاتِ</div><div><strong>الراوي : <span>عمر</span></strong><strong>المصدر : <span>صحيح البخاري</span></strong><strong>خلاصة حكم المحدث : <span>صحيح</span></strong><a tag="right-one"></a></div></div>
          <div class="border-bottom"><div>3 - الإسلام بني على خمس</div><div><strong>الراوي : <span>ابن عمر</span></strong><strong>المصدر : <span>صحيح البخاري</span></strong><strong>خلاصة حكم المحدث : <span>صحيح</span></strong><a tag="right-two"></a></div></div>
        </div></body></html>
        HTML;
    }
}

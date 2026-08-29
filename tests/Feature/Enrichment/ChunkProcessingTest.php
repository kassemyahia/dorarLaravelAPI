<?php

namespace Tests\Feature\Enrichment;

use App\Jobs\Enrichment\ProcessHadithImportJob;
use App\Models\HadithImportJob;
use App\Services\Enrichment\DorarEnrichmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ChunkProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_record_wrapper_is_processed_from_local_disk_without_duplicates(): void
    {
        Queue::fake();
        Storage::fake('local');
        $payload = file_get_contents(base_path('tests/Fixtures/by_book_sample.json'));
        Storage::disk('local')->put('enrichment_uploads/sample.json', $payload);
        $job = HadithImportJob::create(['filename' => 'sample.json', 'original_file_path' => 'enrichment_uploads/sample.json', 'original_wrapper' => array_diff_key(json_decode($payload, true), ['hadiths' => true]), 'total_hadiths' => 2, 'delay_ms' => 1000, 'status' => 'pending']);
        $fake = Mockery::mock(DorarEnrichmentService::class);
        $fake->shouldReceive('enrichHadith')->twice()->andReturnUsing(fn ($text) => ['matched' => true, 'confidence' => 1.0, 'needs_review' => false, 'error_type' => null, 'error_message' => null, 'dorar' => ['rawi' => null, 'mohdith' => null, 'mohdithId' => null, 'book' => 'صحيح البخاري', 'bookId' => '6216', 'numberOrPage' => null, 'grade' => 'صحيح', 'explainGrade' => null, 'takhrij' => null, 'hadithId' => '1', 'url' => null, 'sharh' => null, 'source' => 'Dorar']]);
        $this->app->instance(DorarEnrichmentService::class, $fake);
        app()->call([new ProcessHadithImportJob($job->id), 'handle']);
        $this->assertDatabaseCount('hadith_enrichment_records', 2);
        app()->call([new ProcessHadithImportJob($job->id), 'handle']);
        $this->assertDatabaseCount('hadith_enrichment_records', 2);
        $this->assertSame('completed', $job->refresh()->status);
        $this->assertSame(2, $job->processed_count);
    }
}

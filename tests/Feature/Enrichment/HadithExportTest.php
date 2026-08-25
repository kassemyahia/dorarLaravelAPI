<?php

namespace Tests\Feature\Enrichment;

use App\Models\HadithImportJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HadithExportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test JSON export.
     */
    public function test_export_as_json(): void
    {
        $job = HadithImportJob::create([
            'filename' => 'test.json',
            'total_hadiths' => 1,
            'processed_count' => 1,
            'status' => 'completed',
        ]);

        // Create enrichment record
        $job->enrichmentRecords()->create([
            'original_index' => 0,
            'hadith_id' => 1,
            'original_data' => [
                'id' => 1,
                'arabic' => 'الحديث',
                'english' => ['text' => 'The hadith'],
            ],
            'enriched_data' => [
                'id' => 1,
                'arabic' => 'الحديث',
                'english' => ['text' => 'The hadith'],
                'dorar' => ['rawi' => 'رافع'],
                'matching' => ['matched' => true, 'confidence' => 0.95],
            ],
            'status' => 'success',
            'matched' => true,
            'confidence' => 0.95,
        ]);

        $response = $this->getJson("/v1/api/enrichment/jobs/{$job->id}/download/json");

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertEquals('الحديث', $data[0]['arabic']);
    }

    /**
     * Test CSV export.
     */
    public function test_export_as_csv(): void
    {
        $job = HadithImportJob::create([
            'filename' => 'test.json',
            'total_hadiths' => 1,
            'processed_count' => 1,
            'status' => 'completed',
        ]);

        $job->enrichmentRecords()->create([
            'original_index' => 0,
            'hadith_id' => 1,
            'original_data' => [
                'id' => 1,
                'bookId' => 1,
                'arabic' => 'الحديث',
                'english' => ['text' => 'The hadith'],
            ],
            'enriched_data' => [
                'id' => 1,
                'bookId' => 1,
                'arabic' => 'الحديث',
                'english' => ['narrator' => 'أبو بكر', 'text' => 'The hadith'],
                'dorar' => ['rawi' => 'رافع'],
                'matching' => ['matched' => true, 'confidence' => 0.95],
            ],
            'status' => 'success',
            'matched' => true,
            'confidence' => 0.95,
        ]);

        $response = $this->get("/v1/api/enrichment/jobs/{$job->id}/download/csv");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');

        $content = $response->getContent();
        $this->assertStringContainsString('id', $content);
        $this->assertStringContainsString('arabic', $content);
        $this->assertStringContainsString('matched', $content);
    }

    /**
     * Test export with no data returns error.
     */
    public function test_export_with_no_data_returns_error(): void
    {
        $job = HadithImportJob::create([
            'filename' => 'test.json',
            'total_hadiths' => 0,
            'processed_count' => 0,
            'status' => 'pending',
        ]);

        $response = $this->getJson("/v1/api/enrichment/jobs/{$job->id}/download/json");

        $response->assertStatus(422);
    }

    /**
     * Test get export summary.
     */
    public function test_get_export_summary(): void
    {
        $job = HadithImportJob::create([
            'filename' => 'test.json',
            'total_hadiths' => 2,
            'processed_count' => 2,
            'matched_count' => 1,
            'not_found_count' => 1,
            'status' => 'completed',
        ]);

        $job->enrichmentRecords()->createMany([
            [
                'original_index' => 0,
                'original_data' => [],
                'enriched_data' => [],
                'status' => 'success',
                'matched' => true,
                'confidence' => 0.95,
                'needs_review' => false,
            ],
            [
                'original_index' => 1,
                'original_data' => [],
                'enriched_data' => [],
                'status' => 'success',
                'matched' => false,
                'confidence' => 0.0,
                'needs_review' => false,
            ],
        ]);

        $response = $this->getJson("/v1/api/enrichment/jobs/{$job->id}/summary");

        $response->assertStatus(200);
        $response->assertJsonPath('data.total_records', 2);
        $response->assertJsonPath('data.successful', 2);
        $response->assertJsonPath('data.matched', 1);
    }
}

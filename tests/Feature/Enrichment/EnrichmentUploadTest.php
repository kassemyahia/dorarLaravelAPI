<?php

namespace Tests\Feature\Enrichment;

use App\Models\HadithImportJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EnrichmentUploadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test successful hadith file upload.
     */
    public function test_upload_valid_hadith_file(): void
    {
        Queue::fake();
        $jsonData = ['id' => 1, 'metadata' => ['english' => ['title' => 'Sahih al-Bukhari']], 'chapters' => [], 'hadiths' => [
            [
                'id' => 1,
                'chapterId' => 1,
                'bookId' => 1,
                'arabic' => 'إِنَّمَا الأَعْمَالُ بِالنِّيَّاتِ',
                'english' => [
                    'narrator' => 'Umar ibn al-Khattab',
                    'text' => 'Verily, actions are but by intentions.',
                ],
            ],
        ]];

        $response = $this->postJson('/v1/api/enrichment/import', [
            'file' => $this->createJsonFile($jsonData),
            'delay_ms' => 5000,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => ['jobId', 'status', 'total'],
        ]);

        $this->assertDatabaseHas('hadith_import_jobs', [
            'total_hadiths' => 1,
            'status' => 'pending',
        ]);
    }

    /**
     * Test upload without file fails.
     */
    public function test_upload_without_file_fails(): void
    {
        $response = $this->postJson('/v1/api/enrichment/import', []);

        $response->assertStatus(422);
    }

    /**
     * Test upload with invalid JSON fails.
     */
    public function test_upload_with_invalid_json_fails(): void
    {
        $response = $this->postJson('/v1/api/enrichment/import', [
            'file' => $this->createFile('invalid json {'),
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test upload with invalid structure fails.
     */
    public function test_upload_with_missing_required_fields_fails(): void
    {
        $jsonData = ['id' => 1, 'metadata' => [], 'chapters' => [], 'hadiths' => [
            [
                'id' => 1,
                // Missing 'arabic' and 'english'
            ],
        ]];

        $response = $this->postJson('/v1/api/enrichment/import', [
            'file' => $this->createJsonFile($jsonData),
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test get job status.
     */
    public function test_get_job_status(): void
    {
        $job = HadithImportJob::create([
            'filename' => 'test.json',
            'total_hadiths' => 100,
            'processed_count' => 50,
            'matched_count' => 40,
            'not_found_count' => 10,
            'failed_count' => 0,
            'needs_review_count' => 5,
            'status' => 'processing',
        ]);

        $response = $this->getJson("/v1/api/enrichment/jobs/{$job->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.jobId', $job->id);
        $response->assertJsonPath('data.status', 'processing');
        $response->assertJsonPath('data.progress.percentage', 50);
    }

    /**
     * Test pause job.
     */
    public function test_pause_job(): void
    {
        $job = HadithImportJob::create([
            'filename' => 'test.json',
            'total_hadiths' => 100,
            'status' => 'processing',
        ]);

        $response = $this->postJson("/v1/api/enrichment/jobs/{$job->id}/pause");

        $response->assertStatus(200);
        $this->assertDatabaseHas('hadith_import_jobs', [
            'id' => $job->id,
            'status' => 'paused',
        ]);
    }

    /**
     * Test resume job.
     */
    public function test_resume_job(): void
    {
        Queue::fake();
        $job = HadithImportJob::create([
            'filename' => 'test.json',
            'total_hadiths' => 100,
            'status' => 'paused',
        ]);

        $response = $this->postJson("/v1/api/enrichment/jobs/{$job->id}/resume");

        $response->assertStatus(200);
        $this->assertDatabaseHas('hadith_import_jobs', [
            'id' => $job->id,
            'status' => 'pending',
        ]);
    }

    /**
     * Test cancel job.
     */
    public function test_cancel_job(): void
    {
        $job = HadithImportJob::create([
            'filename' => 'test.json',
            'total_hadiths' => 100,
            'status' => 'processing',
        ]);

        $response = $this->postJson("/v1/api/enrichment/jobs/{$job->id}/cancel");

        $response->assertStatus(200);
        $this->assertDatabaseHas('hadith_import_jobs', [
            'id' => $job->id,
            'status' => 'cancelled',
        ]);
    }

    /**
     * Test cannot cancel completed job.
     */
    public function test_cannot_cancel_completed_job(): void
    {
        $job = HadithImportJob::create([
            'filename' => 'test.json',
            'total_hadiths' => 100,
            'status' => 'completed',
        ]);

        $response = $this->postJson("/v1/api/enrichment/jobs/{$job->id}/cancel");

        $response->assertStatus(409);
    }

    /**
     * Test get nonexistent job returns 404.
     */
    public function test_get_nonexistent_job_returns_404(): void
    {
        $response = $this->getJson('/v1/api/enrichment/jobs/9999');

        $response->assertStatus(404);
    }

    public function test_enrichment_page_contains_csrf_token(): void
    {
        $this->get('/tools/enrichment')->assertOk()->assertSee('name="csrf-token"', false)->assertSee('X-CSRF-TOKEN');
    }

    private function createJsonFile(array $data): UploadedFile
    {
        $json = json_encode($data);
        $path = tempnam(sys_get_temp_dir(), 'hadith_');
        file_put_contents($path, $json);

        return new UploadedFile($path, 'test.json', 'application/json', null, true);
    }

    private function createFile(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($path, $content);

        return new UploadedFile($path, 'test.json', 'application/json', null, true);
    }
}

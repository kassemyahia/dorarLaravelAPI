<?php

namespace App\Jobs\Enrichment;

use App\Models\HadithImportJob as HadithImportJobModel;
use App\Services\Enrichment\HadithEnrichmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Background job for processing hadith enrichment.
 * Reads from uploaded file and enriches hadiths via Dorar.
 * Resumable from last saved progress.
 */
class ProcessHadithImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0; // No timeout for long-running jobs

    public $tries = 1;   // Don't retry the job itself; error handling is internal

    private int $batchSize = 100; // Process in batches

    public function __construct(
        private readonly int $importJobId,
    ) {}

    public function handle(HadithEnrichmentService $enrichmentService): void
    {
        $job = HadithImportJobModel::find($this->importJobId);

        if (! $job) {
            Log::error('Import job not found', ['id' => $this->importJobId]);

            return;
        }

        if ($job->isCancelled()) {
            Log::info('Import job cancelled', ['id' => $job->id]);

            return;
        }

        // Update status
        $job->update(['status' => 'processing', 'started_at' => now()]);

        try {
            // Load hadith data from file
            $filePath = storage_path('app/'.$job->original_file_path);

            if (! file_exists($filePath)) {
                throw new \Exception('Import file not found: '.$filePath);
            }

            $fileContent = file_get_contents($filePath);
            $hadiths = json_decode($fileContent, associative: true);

            if (! is_array($hadiths)) {
                throw new \Exception('Invalid JSON: expected array of hadiths');
            }

            // Process from current index
            $currentIndex = $job->current_index ?? 0;

            Log::info('Starting hadith import', [
                'job_id' => $job->id,
                'total' => count($hadiths),
                'starting_from' => $currentIndex,
            ]);

            // Process in batches
            $totalProcessed = 0;

            for ($i = $currentIndex; $i < count($hadiths); $i += $this->batchSize) {
                if ($job->refresh()->isPaused() || $job->refresh()->isCancelled()) {
                    Log::info('Import job paused/cancelled', ['id' => $job->id, 'at_index' => $i]);

                    return;
                }

                $batch = array_slice($hadiths, $i, $this->batchSize);

                $results = $enrichmentService->enrichBatch($job, $batch, $i);

                // Update job progress
                $job->increment('processed_count', $results['processed']);
                $job->increment('matched_count', $results['matched']);
                $job->increment('not_found_count', $results['not_found']);
                $job->increment('failed_count', $results['failed']);
                $job->increment('needs_review_count', $results['needs_review']);
                $job->update(['current_index' => $i + $this->batchSize]);

                $totalProcessed += $results['processed'];

                Log::info('Batch processed', [
                    'job_id' => $job->id,
                    'batch_start' => $i,
                    'batch_size' => count($batch),
                    'processed' => $results['processed'],
                    'matched' => $results['matched'],
                    'failed' => $results['failed'],
                ]);
            }

            // Mark as completed
            $job->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            Log::info('Import job completed', [
                'job_id' => $job->id,
                'total_processed' => $totalProcessed,
                'matched' => $job->matched_count,
                'failed' => $job->failed_count,
            ]);
        } catch (\Exception $e) {
            Log::error('Import job failed', [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $job->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }
    }
}

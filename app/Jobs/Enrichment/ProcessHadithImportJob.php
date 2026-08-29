<?php

namespace App\Jobs\Enrichment;

use App\Models\HadithImportJob;
use App\Services\Enrichment\HadithEnrichmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ProcessHadithImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public readonly int $importJobId, public readonly int $chunkSize = 10) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('hadith-import-'.$this->importJobId))->releaseAfter(5)->expireAfter(650)];
    }

    public function handle(HadithEnrichmentService $service): void
    {
        $job = HadithImportJob::find($this->importJobId);
        if (! $job || $job->isPaused() || $job->isCancelled() || $job->isCompleted()) {
            return;
        }
        try {
            if (! Storage::disk('local')->exists($job->original_file_path)) {
                throw new \RuntimeException('Uploaded JSON file is missing from local storage');
            }
            $payload = json_decode(Storage::disk('local')->get($job->original_file_path), true, flags: JSON_THROW_ON_ERROR);
            $hadiths = $payload['hadiths'] ?? null;
            if (! is_array($hadiths)) {
                throw new \RuntimeException('Uploaded JSON no longer contains a hadiths array');
            }
            $job->update(['status' => 'processing', 'started_at' => $job->started_at ?? now(), 'error_message' => null]);
            $start = (int) $job->current_index;
            $service->enrichBatch($job, array_slice($hadiths, $start, $this->chunkSize), $start);
            $this->refreshCounters($job, min(count($hadiths), $start + $this->chunkSize));
            $job->refresh();
            if ($job->current_index >= $job->total_hadiths) {
                $job->update(['status' => 'completed', 'completed_at' => now()]);
            } elseif ($job->status === 'processing') {
                self::dispatch($job->id, $this->chunkSize);
            }
        } catch (\Throwable $e) {
            $job->update(['status' => 'failed', 'error_message' => $e->getMessage(), 'completed_at' => now()]);
        }
    }

    private function refreshCounters(HadithImportJob $job, int $next): void
    {
        $q = $job->enrichmentRecords();
        $counts = (clone $q)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $job->update(['current_index' => $next, 'processed_count' => $counts->sum(), 'matched_count' => $counts['matched'] ?? 0, 'not_found_count' => $counts['not_found'] ?? 0, 'needs_review_count' => $counts['needs_review'] ?? 0, 'request_failed_count' => $counts['request_failed'] ?? 0, 'parsing_failed_count' => $counts['parsing_failed'] ?? 0, 'failed_count' => ($counts['request_failed'] ?? 0) + ($counts['parsing_failed'] ?? 0)]);
    }
}

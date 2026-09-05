<?php

namespace App\Jobs\Enrichment;

use App\Models\HadithImportJob;
use App\Services\Enrichment\HadithEnrichmentService;
use App\Services\Enrichment\HadithImportChunkService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessHadithImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 600;

    public function __construct(public readonly int $importJobId, public readonly int $chunkSize = 5) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('hadith-import-'.$this->importJobId))->releaseAfter(5)->expireAfter(650)];
    }

    public function handle(HadithEnrichmentService $service, HadithImportChunkService $chunks): void
    {
        $job = HadithImportJob::find($this->importJobId);
        if (! $job || $job->isPaused() || $job->isCancelled() || $job->isCompleted()) {
            return;
        }
        try {
            $manifest = $chunks->prepare($job, $this->chunkSize);
            $job->refresh();
            $job->update(['status' => 'processing', 'started_at' => $job->started_at ?? now(), 'error_message' => null]);
            $processed = $job->enrichmentRecords()->where('status', '!=', 'pending')->pluck('original_index')->flip();
            $chunk = collect($manifest['chunks'])->first(fn (array $item) => collect(range($item['start'], $item['end']))->contains(fn (int $index) => ! $processed->has($index)));
            if ($chunk) {
                $rows = json_decode(file_get_contents(Storage::disk('local')->path($chunk['path'])), true, flags: JSON_THROW_ON_ERROR);
                $service->enrichChunk($job, $rows);
            }
            $this->refreshCounters($job);
            $job->refresh();
            if ($job->processed_count >= $job->total_hadiths) {
                $job->update(['status' => 'completed', 'completed_at' => now()]);
            } elseif ($job->status === 'processing') {
                self::dispatch($job->id, $this->chunkSize);
            }
        } catch (Throwable $e) {
            $job->update(['status' => 'failed', 'error_message' => $e->getMessage(), 'completed_at' => now()]);
        }
    }

    private function refreshCounters(HadithImportJob $job): void
    {
        $q = $job->enrichmentRecords();
        $counts = (clone $q)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $lastError = $job->enrichmentRecords()->whereIn('status', ['request_failed', 'parsing_failed'])->latest('original_index')->value('error_message');
        $done = $job->enrichmentRecords()->where('status', '!=', 'pending')->pluck('original_index')->flip();
        $next = 0;
        while ($next < $job->total_hadiths && $done->has($next)) {
            $next++;
        }
        $job->update(['current_index' => $next, 'processed_count' => $counts->sum(), 'matched_count' => $counts['matched'] ?? 0, 'not_found_count' => $counts['not_found'] ?? 0, 'needs_review_count' => $counts['needs_review'] ?? 0, 'request_failed_count' => $counts['request_failed'] ?? 0, 'parsing_failed_count' => $counts['parsing_failed'] ?? 0, 'failed_count' => ($counts['request_failed'] ?? 0) + ($counts['parsing_failed'] ?? 0), 'error_message' => $lastError]);
    }

    public function failed(Throwable $exception): void
    {
        HadithImportJob::whereKey($this->importJobId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->update(['status' => 'failed', 'error_message' => $exception->getMessage(), 'completed_at' => now()]);
    }
}

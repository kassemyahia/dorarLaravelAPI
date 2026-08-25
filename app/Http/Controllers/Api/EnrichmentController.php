<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Enrichment\UploadHadithFileRequest;
use App\Jobs\Enrichment\ProcessHadithImportJob;
use App\Models\HadithImportJob;
use App\Services\Enrichment\HadithExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EnrichmentController extends BaseApiController
{
    public function __construct(
        private readonly HadithExportService $exportService,
    ) {}

    /**
     * Upload and start hadith enrichment job.
     * POST /v1/api/enrichment/import
     */
    public function upload(UploadHadithFileRequest $request): JsonResponse
    {
        try {
            $file = $request->file('file');

            // Validate JSON structure
            $fileContent = $file->get();
            $hadiths = json_decode($fileContent, associative: true);

            if (! is_array($hadiths) || empty($hadiths)) {
                return $this->sendError('Invalid JSON: expected non-empty array of hadiths', 422);
            }

            // Sample validation: check first hadith structure
            $firstHadith = reset($hadiths);
            if (! is_array($firstHadith)) {
                return $this->sendError('Invalid JSON: array items must be objects', 422);
            }

            if (! isset($firstHadith['arabic']) || ! isset($firstHadith['english'])) {
                return $this->sendError('Invalid hadith structure: missing required fields (arabic, english)', 422);
            }

            // Store file
            $fileName = 'hadith_'.time().'_'.uniqid().'.json';
            $filePath = "enrichment_uploads/{$fileName}";
            Storage::disk('local')->put($filePath, $fileContent);

            // Create import job
            $importJob = HadithImportJob::create([
                'filename' => $file->getClientOriginalName(),
                'original_file_path' => $filePath,
                'total_hadiths' => count($hadiths),
                'processed_count' => 0,
                'matched_count' => 0,
                'not_found_count' => 0,
                'failed_count' => 0,
                'needs_review_count' => 0,
                'current_index' => 0,
                'status' => 'pending',
                'delay_ms' => (int) $request->input('delay_ms', 5000),
                'confidence_threshold_low' => (float) $request->input('confidence_threshold_low', 0.80),
                'confidence_threshold_medium' => (float) $request->input('confidence_threshold_medium', 0.95),
            ]);

            // Dispatch background job
            ProcessHadithImportJob::dispatch($importJob->id);

            Log::info('Hadith import job created', [
                'job_id' => $importJob->id,
                'total_hadiths' => count($hadiths),
                'file' => $fileName,
            ]);

            return $this->sendSuccess(201, [
                'jobId' => $importJob->id,
                'status' => $importJob->status,
                'total' => $importJob->total_hadiths,
            ]);
        } catch (\Exception $e) {
            Log::error('Error uploading hadith file', [
                'error' => $e->getMessage(),
            ]);

            return $this->sendError('Error processing upload: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get import job status and progress.
     * GET /v1/api/enrichment/jobs/{id}
     */
    public function getStatus(int $id): JsonResponse
    {
        $job = HadithImportJob::find($id);

        if (! $job) {
            return $this->sendError('Job not found', 404);
        }

        return $this->sendSuccess(200, [
            'jobId' => $job->id,
            'status' => $job->status,
            'progress' => [
                'total' => $job->total_hadiths,
                'processed' => $job->processed_count,
                'matched' => $job->matched_count,
                'notFound' => $job->not_found_count,
                'failed' => $job->failed_count,
                'needsReview' => $job->needs_review_count,
                'percentage' => round($job->getProgressPercentage(), 2),
            ],
            'stats' => [
                'startedAt' => $job->started_at?->toIso8601String(),
                'completedAt' => $job->completed_at?->toIso8601String(),
                'pausedAt' => $job->paused_at?->toIso8601String(),
            ],
            'settings' => [
                'delayMs' => $job->delay_ms,
                'confidenceThresholdLow' => $job->confidence_threshold_low,
                'confidenceThresholdMedium' => $job->confidence_threshold_medium,
            ],
        ]);
    }

    /**
     * Pause import job.
     * POST /v1/api/enrichment/jobs/{id}/pause
     */
    public function pause(int $id): JsonResponse
    {
        $job = HadithImportJob::find($id);

        if (! $job) {
            return $this->sendError('Job not found', 404);
        }

        if (! in_array($job->status, ['pending', 'processing'], true)) {
            return $this->sendError('Cannot pause a '.$job->status.' job', 409);
        }

        $job->update([
            'status' => 'paused',
            'paused_at' => now(),
        ]);

        Log::info('Import job paused', ['job_id' => $job->id]);

        return $this->sendSuccess(200, [
            'jobId' => $job->id,
            'status' => $job->status,
        ]);
    }

    /**
     * Resume import job.
     * POST /v1/api/enrichment/jobs/{id}/resume
     */
    public function resume(int $id): JsonResponse
    {
        $job = HadithImportJob::find($id);

        if (! $job) {
            return $this->sendError('Job not found', 404);
        }

        if ($job->status !== 'paused') {
            return $this->sendError('Can only resume paused jobs', 409);
        }

        $job->update([
            'status' => 'pending',
            'paused_at' => null,
        ]);

        // Dispatch job again
        ProcessHadithImportJob::dispatch($job->id);

        Log::info('Import job resumed', ['job_id' => $job->id]);

        return $this->sendSuccess(200, [
            'jobId' => $job->id,
            'status' => $job->status,
        ]);
    }

    /**
     * Cancel import job.
     * POST /v1/api/enrichment/jobs/{id}/cancel
     */
    public function cancel(int $id): JsonResponse
    {
        $job = HadithImportJob::find($id);

        if (! $job) {
            return $this->sendError('Job not found', 404);
        }

        if (in_array($job->status, ['completed', 'failed', 'cancelled'], true)) {
            return $this->sendError('Cannot cancel a '.$job->status.' job', 409);
        }

        $job->update([
            'status' => 'cancelled',
            'completed_at' => now(),
        ]);

        Log::info('Import job cancelled', ['job_id' => $job->id]);

        return $this->sendSuccess(200, [
            'jobId' => $job->id,
            'status' => $job->status,
        ]);
    }

    /**
     * Download enriched hadiths as JSON.
     * GET /v1/api/enrichment/jobs/{id}/download/json
     */
    public function downloadJson(int $id): JsonResponse
    {
        $job = HadithImportJob::find($id);

        if (! $job) {
            return $this->sendError('Job not found', 404);
        }

        if ($job->processed_count === 0) {
            return $this->sendError('No data to export', 422);
        }

        $json = $this->exportService->exportAsJson($job);

        return response()->json(
            json_decode($json, associative: true),
            200,
            ['Content-Disposition' => 'attachment; filename="hadith_enriched_'.$job->id.'.json"'],
        );
    }

    /**
     * Download enriched hadiths as CSV.
     * GET /v1/api/enrichment/jobs/{id}/download/csv
     */
    public function downloadCsv(int $id)
    {
        $job = HadithImportJob::find($id);

        if (! $job) {
            return $this->sendError('Job not found', 404);
        }

        if ($job->processed_count === 0) {
            return $this->sendError('No data to export', 422);
        }

        $csv = $this->exportService->exportAsCsv($job);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="hadith_enriched_'.$job->id.'.csv"',
        ]);
    }

    /**
     * Get export summary.
     * GET /v1/api/enrichment/jobs/{id}/summary
     */
    public function getSummary(int $id): JsonResponse
    {
        $job = HadithImportJob::find($id);

        if (! $job) {
            return $this->sendError('Job not found', 404);
        }

        $summary = $this->exportService->getExportSummary($job);

        return $this->sendSuccess(200, $summary);
    }
}

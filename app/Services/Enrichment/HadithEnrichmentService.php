<?php

namespace App\Services\Enrichment;

use App\Models\HadithEnrichmentRecord;
use App\Models\HadithImportJob;

/**
 * Orchestrates the hadith enrichment workflow.
 * Processes hadiths, enriches them, and stores results.
 */
class HadithEnrichmentService
{
    public function __construct(
        private readonly DorarEnrichmentService $dorarService,
    ) {}

    /**
     * Enrich a batch of hadiths.
     * Returns summary statistics.
     */
    public function enrichBatch(
        HadithImportJob $job,
        array $hadiths,
        int $startIndex = 0,
    ): array {
        $results = [
            'processed' => 0,
            'matched' => 0,
            'not_found' => 0,
            'failed' => 0,
            'needs_review' => 0,
            'errors' => [],
        ];

        foreach ($hadiths as $index => $hadith) {
            $currentIndex = $startIndex + $index;

            try {
                $enrichment = $this->enrichSingleHadith($job, $hadith, $currentIndex);

                $results['processed']++;

                if ($enrichment['error_type'] === null) {
                    if ($enrichment['matched']) {
                        $results['matched']++;
                    } else {
                        $results['not_found']++;
                    }

                    if ($enrichment['needs_review']) {
                        $results['needs_review']++;
                    }
                } else {
                    $results['failed']++;
                    $results['errors'][] = [
                        'index' => $currentIndex,
                        'hadith_id' => $hadith['id'] ?? 'unknown',
                        'error_type' => $enrichment['error_type'],
                        'error_message' => $enrichment['error_message'],
                    ];
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['processed']++;

                $errorData = [
                    'index' => $currentIndex,
                    'hadith_id' => $hadith['id'] ?? 'unknown',
                    'error_type' => 'UNKNOWN',
                    'error_message' => $e->getMessage(),
                ];

                $results['errors'][] = $errorData;

                // Still create a failed record
                HadithEnrichmentRecord::create([
                    'import_job_id' => $job->id,
                    'original_index' => $currentIndex,
                    'hadith_id' => $hadith['id'] ?? null,
                    'original_data' => $hadith,
                    'enriched_data' => null,
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'error_type' => 'UNKNOWN',
                    'matched' => false,
                    'confidence' => 0.0,
                    'needs_review' => false,
                    'processed_at' => now(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Enrich a single hadith record.
     */
    private function enrichSingleHadith(
        HadithImportJob $job,
        array $hadith,
        int $index,
    ): array {
        // Extract Arabic text
        $arabicText = $hadith['arabic'] ?? null;
        if (! $arabicText || trim($arabicText) === '') {
            return [
                'matched' => false,
                'confidence' => 0.0,
                'needs_review' => false,
                'error_type' => 'PARSING_FAILED',
                'error_message' => 'No Arabic text found in hadith',
                'dorar' => null,
            ];
        }

        // Enrich from Dorar
        $enrichment = $this->dorarService->enrichHadith(
            hadithText: $arabicText,
            bookId: $hadith['bookId'] ?? null,
            delayMs: $job->delay_ms,
        );

        // Prepare enriched data
        $enrichedData = array_merge($hadith, [
            'dorar' => $enrichment['dorar'],
            'matching' => [
                'matched' => $enrichment['matched'],
                'confidence' => $enrichment['confidence'],
                'needs_review' => $enrichment['needs_review'],
            ],
        ]);

        // Determine record status
        $recordStatus = $enrichment['error_type'] === null ? 'success' : 'failed';

        // Store record
        HadithEnrichmentRecord::create([
            'import_job_id' => $job->id,
            'original_index' => $index,
            'hadith_id' => $hadith['id'] ?? null,
            'original_data' => $hadith,
            'enriched_data' => $enrichedData,
            'status' => $recordStatus,
            'error_message' => $enrichment['error_message'] ?? null,
            'error_type' => $enrichment['error_type'],
            'matched' => $enrichment['matched'],
            'confidence' => $enrichment['confidence'],
            'needs_review' => $enrichment['needs_review'],
            'processed_at' => now(),
        ]);

        return $enrichment;
    }
}

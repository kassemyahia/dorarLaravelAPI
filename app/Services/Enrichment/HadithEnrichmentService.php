<?php

namespace App\Services\Enrichment;

use App\Models\HadithEnrichmentRecord;
use App\Models\HadithImportJob;

class HadithEnrichmentService
{
    public function __construct(private readonly DorarEnrichmentService $dorar) {}

    public function enrichBatch(HadithImportJob $job, array $hadiths, int $startIndex = 0): void
    {
        foreach ($hadiths as $offset => $hadith) {
            $index = $startIndex + $offset;
            if (HadithEnrichmentRecord::where(['import_job_id' => $job->id, 'original_index' => $index])->where('status', '!=', 'pending')->exists()) {
                continue;
            }
            if (! is_array($hadith)) {
                $this->store($job, $index, [], 'parsing_failed', 'PARSING_FAILED', 'Hadith must be an object');

                continue;
            }
            $text = $hadith['arabic'] ?? null;
            if (! is_string($text) || trim($text) === '') {
                $this->store($job, $index, $hadith, 'parsing_failed', 'PARSING_FAILED', 'Missing non-empty arabic field');

                continue;
            }
            $result = $this->dorar->enrichHadith($text, isset($hadith['bookId']) ? (string) $hadith['bookId'] : null, $job->delay_ms, $job->confidence_threshold_low, $job->confidence_threshold_medium, $job->original_wrapper['metadata']['english']['title'] ?? null, $hadith['idInBook'] ?? null);
            $status = match ($result['error_type']) {
                null => $result['needs_review'] ? 'needs_review' : 'matched', 'NOT_FOUND' => 'not_found', 'PARSING_FAILED' => 'parsing_failed', 'MATCH_LOW_CONFIDENCE' => 'needs_review', default => 'request_failed'
            };
            $enriched = $hadith;
            $enriched['dorar'] = $result['dorar'];
            $enriched['matching'] = ['matched' => $result['matched'], 'confidence' => $result['confidence'], 'needsReview' => $result['needs_review'], 'status' => $status];
            if (isset($result['diagnostics'])) {
                $enriched['matching']['diagnostics'] = $result['diagnostics'];
            }
            HadithEnrichmentRecord::updateOrCreate(['import_job_id' => $job->id, 'original_index' => $index], ['hadith_id' => $hadith['id'] ?? null, 'original_data' => $hadith, 'enriched_data' => $enriched, 'status' => $status, 'error_message' => $result['error_message'], 'error_type' => $result['error_type'], 'matched' => $result['matched'], 'confidence' => $result['confidence'], 'needs_review' => $result['needs_review'], 'processed_at' => now()]);
        }
    }

    private function store(HadithImportJob $job, int $index, array $data, string $status, string $type, string $message): void
    {
        $enriched = $data + ['dorar' => ['rawi' => null, 'mohdith' => null, 'mohdithId' => null, 'book' => null, 'bookId' => null, 'numberOrPage' => null, 'grade' => null, 'explainGrade' => null, 'takhrij' => null, 'hadithId' => null, 'url' => null, 'sharh' => null, 'source' => 'Dorar'], 'matching' => ['matched' => false, 'confidence' => 0, 'needsReview' => true, 'status' => $status]];
        HadithEnrichmentRecord::updateOrCreate(['import_job_id' => $job->id, 'original_index' => $index], ['original_data' => $data, 'enriched_data' => $enriched, 'status' => $status, 'error_type' => $type, 'error_message' => $message, 'matched' => false, 'confidence' => 0, 'needs_review' => true, 'processed_at' => now()]);
    }
}

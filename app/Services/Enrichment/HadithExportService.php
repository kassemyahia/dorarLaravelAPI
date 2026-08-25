<?php

namespace App\Services\Enrichment;

use App\Models\HadithImportJob;
use League\Csv\Writer;

/**
 * Exports enriched hadith records as JSON or CSV.
 */
class HadithExportService
{
    /**
     * Export enriched records as JSON.
     * Preserves all original fields + enrichment data.
     */
    public function exportAsJson(HadithImportJob $job): string
    {
        $records = $job->enrichmentRecords()
            ->where('status', 'success')
            ->orWhere('status', 'failed')
            ->get();

        $data = [];
        foreach ($records as $record) {
            if ($record->enriched_data) {
                $data[] = $record->enriched_data;
            } elseif ($record->original_data) {
                $data[] = $record->original_data;
            }
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Export enriched records as CSV.
     * Flattens nested JSON structures.
     */
    public function exportAsCsv(HadithImportJob $job): string
    {
        $records = $job->enrichmentRecords()
            ->where('status', 'success')
            ->orWhere('status', 'failed')
            ->get();

        // Use League CSV for proper CSV generation
        $writer = Writer::createFromString('');
        $writer->setDelimiter(',');

        // Header row
        $headers = [
            'id',
            'chapterId',
            'bookId',
            'arabic',
            'english_narrator',
            'english_text',
            'dorar_rawi',
            'dorar_mohdith',
            'dorar_book',
            'dorar_numberOrPage',
            'dorar_grade',
            'dorar_explainGrade',
            'dorar_hadithId',
            'dorar_url',
            'matched',
            'confidence',
            'needs_review',
            'error_type',
        ];
        $writer->insertOne($headers);

        // Data rows
        foreach ($records as $record) {
            $enriched = $record->enriched_data ?? $record->original_data;

            $row = [
                $enriched['id'] ?? '',
                $enriched['chapterId'] ?? '',
                $enriched['bookId'] ?? '',
                $enriched['arabic'] ?? '',
                $enriched['english']['narrator'] ?? '',
                $enriched['english']['text'] ?? '',
                $enriched['dorar']['rawi'] ?? '',
                $enriched['dorar']['mohdith'] ?? '',
                $enriched['dorar']['book'] ?? '',
                $enriched['dorar']['numberOrPage'] ?? '',
                $enriched['dorar']['grade'] ?? '',
                $enriched['dorar']['explainGrade'] ?? '',
                $enriched['dorar']['hadithId'] ?? '',
                $enriched['dorar']['url'] ?? '',
                $record->matched ? 'true' : 'false',
                $record->confidence ?? '',
                $record->needs_review ? 'true' : 'false',
                $record->error_type ?? '',
            ];

            $writer->insertOne($row);
        }

        return (string) $writer->getContent();
    }

    /**
     * Get summary statistics for export.
     */
    public function getExportSummary(HadithImportJob $job): array
    {
        $records = $job->enrichmentRecords()->get();
        $successRecords = $records->where('status', 'success');
        $matchedRecords = $successRecords->where('matched', true);
        $reviewRecords = $successRecords->where('needs_review', true);

        return [
            'total_records' => $records->count(),
            'successful' => $successRecords->count(),
            'failed' => $records->where('status', 'failed')->count(),
            'matched' => $matchedRecords->count(),
            'not_matched' => $successRecords->where('matched', false)->count(),
            'needs_review' => $reviewRecords->count(),
            'average_confidence' => $successRecords->where('matched', true)->avg('confidence'),
        ];
    }
}

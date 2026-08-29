<?php

namespace App\Services\Enrichment;

use App\Models\HadithImportJob;
use League\Csv\Writer;

class HadithExportService
{
    public function exportAsJson(HadithImportJob $job): string
    {
        $records = $job->enrichmentRecords()->orderBy('original_index')->get();
        $wrapper = $job->original_wrapper ?? [];
        $wrapper['hadiths'] = $records->map(fn ($r) => $r->enriched_data ?: $r->original_data)->all();

        return json_encode($wrapper, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    public function exportAsCsv(HadithImportJob $job): string
    {
        $writer = Writer::createFromString("\xEF\xBB\xBF");
        $headers = ['id', 'idInBook', 'chapterId', 'bookId', 'arabic', 'english_narrator', 'english_text', 'dorar_rawi', 'dorar_mohdith', 'dorar_book', 'dorar_numberOrPage', 'dorar_grade', 'dorar_explainGrade', 'dorar_takhrij', 'dorar_hadithId', 'dorar_url', 'dorar_sharh', 'matched', 'confidence', 'needs_review', 'status', 'error_type'];
        $writer->insertOne($headers);
        foreach ($job->enrichmentRecords()->orderBy('original_index')->get() as $record) {
            $e = $record->enriched_data ?: $record->original_data;
            $safe = fn ($v) => is_string($v) && preg_match('/^[=+\-@]/u', $v) ? "'".$v : $v;
            $writer->insertOne(array_map($safe, [$e['id'] ?? '', $e['idInBook'] ?? '', $e['chapterId'] ?? '', $e['bookId'] ?? '', $e['arabic'] ?? '', $e['english']['narrator'] ?? '', $e['english']['text'] ?? '', $e['dorar']['rawi'] ?? '', $e['dorar']['mohdith'] ?? '', $e['dorar']['book'] ?? '', $e['dorar']['numberOrPage'] ?? '', $e['dorar']['grade'] ?? '', $e['dorar']['explainGrade'] ?? '', $e['dorar']['takhrij'] ?? '', $e['dorar']['hadithId'] ?? '', $e['dorar']['url'] ?? '', $e['dorar']['sharh'] ?? '', $record->matched ? 'true' : 'false', $record->confidence ?? '', $record->needs_review ? 'true' : 'false', $record->status, $record->error_type ?? '']));
        }

        return $writer->toString();
    }

    public function getExportSummary(HadithImportJob $job): array
    {
        $q = $job->enrichmentRecords();

        return ['total_records' => (clone $q)->count(), 'successful' => (clone $q)->whereIn('status', ['success', 'matched', 'not_found', 'needs_review'])->count(), 'failed' => (clone $q)->whereIn('status', ['failed', 'request_failed', 'parsing_failed'])->count(), 'matched' => (clone $q)->where('matched', true)->count(), 'not_matched' => (clone $q)->where('matched', false)->whereIn('status', ['success', 'not_found'])->count(), 'needs_review' => (clone $q)->where('needs_review', true)->count(), 'average_confidence' => (clone $q)->where('matched', true)->avg('confidence')];
    }
}

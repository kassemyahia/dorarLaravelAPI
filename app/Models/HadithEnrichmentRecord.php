<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HadithEnrichmentRecord extends Model
{
    use HasFactory;

    protected $table = 'hadith_enrichment_records';

    protected $fillable = [
        'import_job_id',
        'original_index',
        'hadith_id',
        'original_data',
        'enriched_data',
        'status',
        'error_message',
        'error_type',
        'matched',
        'confidence',
        'needs_review',
        'processed_at',
    ];

    protected $casts = [
        'original_data' => 'json',
        'enriched_data' => 'json',
        'matched' => 'boolean',
        'confidence' => 'float',
        'needs_review' => 'boolean',
        'processed_at' => 'datetime',
    ];

    public function importJob()
    {
        return $this->belongsTo(HadithImportJob::class, 'import_job_id');
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isMatched(): bool
    {
        return $this->matched === true;
    }

    public function needsReview(): bool
    {
        return $this->needs_review === true;
    }
}

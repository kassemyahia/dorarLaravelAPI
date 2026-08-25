<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HadithImportJob extends Model
{
    use HasFactory;

    protected $table = 'hadith_import_jobs';

    protected $fillable = [
        'filename',
        'original_file_path',
        'total_hadiths',
        'processed_count',
        'matched_count',
        'not_found_count',
        'failed_count',
        'needs_review_count',
        'current_index',
        'status',
        'started_at',
        'completed_at',
        'paused_at',
        'error_message',
        'delay_ms',
        'confidence_threshold_low',
        'confidence_threshold_medium',
    ];

    protected $casts = [
        'total_hadiths' => 'integer',
        'processed_count' => 'integer',
        'matched_count' => 'integer',
        'not_found_count' => 'integer',
        'failed_count' => 'integer',
        'needs_review_count' => 'integer',
        'current_index' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'paused_at' => 'datetime',
        'delay_ms' => 'integer',
        'confidence_threshold_low' => 'float',
        'confidence_threshold_medium' => 'float',
    ];

    public function enrichmentRecords()
    {
        return $this->hasMany(HadithEnrichmentRecord::class, 'import_job_id');
    }

    public function getProgressPercentage(): float
    {
        if ($this->total_hadiths === 0) {
            return 0;
        }

        return ($this->processed_count / $this->total_hadiths) * 100;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}

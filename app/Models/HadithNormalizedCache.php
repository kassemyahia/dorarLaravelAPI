<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HadithNormalizedCache extends Model
{
    use HasFactory;

    protected $table = 'hadith_normalized_caches';

    protected $fillable = [
        'normalized_hash',
        'original_text',
        'dorar_result',
        'matched',
        'confidence',
        'needs_review',
        'error_type',
        'cached_at',
    ];

    protected $casts = [
        'dorar_result' => 'json',
        'matched' => 'boolean',
        'confidence' => 'float',
        'needs_review' => 'boolean',
        'cached_at' => 'datetime',
    ];

    public function isMatched(): bool
    {
        return $this->matched === true;
    }

    public function needsReview(): bool
    {
        return $this->needs_review === true;
    }
}

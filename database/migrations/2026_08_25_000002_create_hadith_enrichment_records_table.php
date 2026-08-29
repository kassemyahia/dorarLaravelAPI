<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hadith_enrichment_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_job_id')->constrained('hadith_import_jobs')->onDelete('cascade');
            $table->integer('original_index');
            $table->string('hadith_id')->nullable();
            $table->json('original_data');
            $table->json('enriched_data')->nullable();
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->enum('error_type', [
                'NOT_FOUND',
                'REQUEST_FAILED',
                'RATE_LIMITED',
                'PARSING_FAILED',
                'MATCH_LOW_CONFIDENCE',
                'TIMEOUT',
                'UNKNOWN',
            ])->nullable();
            $table->boolean('matched')->default(false);
            $table->float('confidence')->nullable();
            $table->boolean('needs_review')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['import_job_id', 'status']);
            $table->unique(['import_job_id', 'original_index']);
            $table->index('hadith_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hadith_enrichment_records');
    }
};

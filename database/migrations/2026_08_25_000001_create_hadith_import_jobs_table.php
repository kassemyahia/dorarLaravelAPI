<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hadith_import_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('original_file_path')->nullable();
            $table->json('original_wrapper')->nullable();
            $table->integer('total_hadiths')->default(0);
            $table->integer('processed_count')->default(0);
            $table->integer('matched_count')->default(0);
            $table->integer('not_found_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->integer('request_failed_count')->default(0);
            $table->integer('parsing_failed_count')->default(0);
            $table->integer('needs_review_count')->default(0);
            $table->integer('current_index')->default(0);
            $table->enum('status', ['pending', 'processing', 'paused', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('delay_ms')->default(5000);
            $table->float('confidence_threshold_low')->default(0.80);
            $table->float('confidence_threshold_medium')->default(0.95);
            $table->timestamps();
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hadith_import_jobs');
    }
};

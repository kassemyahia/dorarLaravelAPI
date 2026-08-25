<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hadith_normalized_caches', function (Blueprint $table) {
            $table->id();
            $table->string('normalized_hash')->unique()->index();
            $table->text('original_text');
            $table->json('dorar_result')->nullable();
            $table->boolean('matched')->default(false);
            $table->float('confidence')->nullable();
            $table->boolean('needs_review')->default(false);
            $table->enum('error_type', [
                'NOT_FOUND',
                'REQUEST_FAILED',
                'RATE_LIMITED',
                'PARSING_FAILED',
                'MATCH_LOW_CONFIDENCE',
                'TIMEOUT',
                'UNKNOWN',
            ])->nullable();
            $table->timestamp('cached_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hadith_normalized_caches');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hadith_import_jobs', function (Blueprint $table) {
            if (! Schema::hasColumn('hadith_import_jobs', 'original_wrapper')) {
                $table->json('original_wrapper')->nullable()->after('original_file_path');
            }
            if (! Schema::hasColumn('hadith_import_jobs', 'request_failed_count')) {
                $table->unsignedInteger('request_failed_count')->default(0)->after('failed_count');
            }
            if (! Schema::hasColumn('hadith_import_jobs', 'parsing_failed_count')) {
                $table->unsignedInteger('parsing_failed_count')->default(0)->after('request_failed_count');
            }
        });

        // Rebuilds SQLite's legacy enum/check column so chunk outcome values are valid.
        Schema::table('hadith_enrichment_records', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });

        $duplicates = DB::table('hadith_enrichment_records')
            ->select('import_job_id', 'original_index', DB::raw('MAX(id) as keep_id'))
            ->groupBy('import_job_id', 'original_index')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('hadith_enrichment_records')
                ->where('import_job_id', $duplicate->import_job_id)
                ->where('original_index', $duplicate->original_index)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }

        $hasUniqueIndex = collect(Schema::getIndexes('hadith_enrichment_records'))->contains(
            fn (array $index) => ($index['unique'] ?? false)
                && ($index['columns'] ?? []) === ['import_job_id', 'original_index']
        );

        if (! $hasUniqueIndex) {
            Schema::table('hadith_enrichment_records', function (Blueprint $table) {
                $table->unique(['import_job_id', 'original_index'], 'hadith_records_import_index_unique');
            });
        }
    }

    public function down(): void
    {
        // This compatibility migration intentionally keeps data and additive columns.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hadith_import_jobs', function (Blueprint $table) {
            $table->string('manifest_path')->nullable()->after('original_file_path');
            $table->string('chunk_directory')->nullable()->after('manifest_path');
        });
    }

    public function down(): void
    {
        Schema::table('hadith_import_jobs', fn (Blueprint $table) => $table->dropColumn(['manifest_path', 'chunk_directory']));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pemeriksaan_lansia', function (Blueprint $table) {
            if (Schema::hasColumn('pemeriksaan_lansia', 'skil_rujuk_otomatis')) {
                $table->dropColumn('skil_rujuk_otomatis');
            }
        });

        Schema::table('pemeriksaan_lansia', function (Blueprint $table) {
            $table->boolean('skil_rujuk_otomatis')
                  ->default(false)
                  ->after('skil_imunisasi_covid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

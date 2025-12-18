<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop dulu kolom generated yang bergantung pada aks_bab_s1_kadang_terkendali
        DB::statement('
            ALTER TABLE pemeriksaan_lansia
            DROP COLUMN aks_total_skor,
            DROP COLUMN aks_kategori,
            DROP COLUMN aks_rujuk_otomatis
        ');

        // 2. Baru rename kolom boolean-nya
        Schema::table('pemeriksaan_lansia', function (Blueprint $table) {
            $table->renameColumn(
                'aks_bab_s1_kadang_terkendali',
                'aks_bab_s1_kadang_tak_terkendali'
            );
        });
    }

    public function down(): void
    {
        // opsional: rollback kebalikan dari up()
        DB::statement('
            ALTER TABLE pemeriksaan_lansia
            DROP COLUMN aks_total_skor,
            DROP COLUMN aks_kategori,
            DROP COLUMN aks_rujuk_otomatis
        ');

        Schema::table('pemeriksaan_lansia', function (Blueprint $table) {
            $table->renameColumn(
                'aks_bab_s1_kadang_tak_terkendali',
                'aks_bab_s1_kadang_terkendali'
            );
        });
    }
};

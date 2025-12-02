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

        // 3. Tambah lagi kolom generated dengan rumus yang pakai nama baru
        DB::statement("
            ALTER TABLE pemeriksaan_lansia
            ADD COLUMN aks_total_skor TINYINT AS (
                IF(aks_bab_s2_terkendali, 2, IF(aks_bab_s1_kadang_tak_terkendali, 1, 0)) +
                IF(aks_bak_s2_mandiri, 2, IF(aks_bak_s1_kadang_1x24jam, 1, 0)) +
                IF(aks_diri_s1_mandiri, 1, 0) +
                IF(aks_wc_s2_mandiri, 2, IF(aks_wc_s1_perlu_beberapa_bisa_sendiri, 1, 0)) +
                IF(aks_makan_s2_mandiri, 2, IF(aks_makan_s1_perlu_pemotongan, 1, 0)) +
                IF(aks_bergerak_s3_mandiri, 3, IF(aks_bergerak_s2_butuh_1orang, 2, IF(aks_bergerak_s1_butuh_2orang, 1, 0))) +
                IF(aks_jalan_s3_mandiri, 3, IF(aks_jalan_s2_bantuan_1orang, 2, IF(aks_jalan_s1_kursi_roda, 1, 0))) +
                IF(aks_pakaian_s2_mandiri, 2, IF(aks_pakaian_s1_sebagian_dibantu, 1, 0)) +
                IF(aks_tangga_s2_mandiri, 2, IF(aks_tangga_s1_butuh_bantuan, 1, 0)) +
                IF(aks_mandi_s1_mandiri, 1, 0)
            ) STORED,
            ADD COLUMN aks_kategori ENUM('M','R','S','B','T') AS (
                CASE
                    WHEN aks_total_skor >= 20 THEN 'M'
                    WHEN aks_total_skor >= 12 THEN 'R'
                    WHEN aks_total_skor >= 9  THEN 'S'
                    WHEN aks_total_skor >= 5  THEN 'B'
                    ELSE 'T'
                END
            ) STORED,
            ADD COLUMN aks_rujuk_otomatis TINYINT(1) AS (aks_total_skor < 20) STORED
        ");
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

        DB::statement("
            ALTER TABLE pemeriksaan_lansia
            ADD COLUMN aks_total_skor TINYINT AS (
                IF(aks_bab_s2_terkendali, 2, IF(aks_bab_s1_kadang_terkendali, 1, 0)) +
                IF(aks_bak_s2_mandiri, 2, IF(aks_bak_s1_kadang_1x24jam, 1, 0)) +
                IF(aks_diri_s1_mandiri, 1, 0) +
                IF(aks_wc_s2_mandiri, 2, IF(aks_wc_s1_perlu_beberapa_bisa_sendiri, 1, 0)) +
                IF(aks_makan_s2_mandiri, 2, IF(aks_makan_s1_perlu_pemotongan, 1, 0)) +
                IF(aks_bergerak_s3_mandiri, 3, IF(aks_bergerak_s2_butuh_1orang, 2, IF(aks_bergerak_s1_butuh_2orang, 1, 0))) +
                IF(aks_jalan_s3_mandiri, 3, IF(aks_jalan_s2_bantuan_1orang, 2, IF(aks_jalan_s1_kursi_roda, 1, 0))) +
                IF(aks_pakaian_s2_mandiri, 2, IF(aks_pakaian_s1_sebagian_dibantu, 1, 0)) +
                IF(aks_tangga_s2_mandiri, 2, IF(aks_tangga_s1_butuh_bantuan, 1, 0)) +
                IF(aks_mandi_s1_mandiri, 1, 0)
            ) STORED,
            ADD COLUMN aks_kategori ENUM('M','R','S','B','T') AS (
                CASE
                    WHEN aks_total_skor >= 20 THEN 'M'
                    WHEN aks_total_skor >= 12 THEN 'R'
                    WHEN aks_total_skor >= 9  THEN 'S'
                    WHEN aks_total_skor >= 5  THEN 'B'
                    ELSE 'T'
                END
            ) STORED,
            ADD COLUMN aks_rujuk_otomatis TINYINT(1) AS (aks_total_skor < 20) STORED
        ");
    }
};

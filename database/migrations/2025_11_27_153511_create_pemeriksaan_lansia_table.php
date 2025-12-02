<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pemeriksaan_lansia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warga_id')->constrained('warga')->onDelete('cascade');
            $table->date('tanggal_periksa');

            // ==================== AKS (Barthel Index) – 30 kolom boolean ====================
            $table->boolean('aks_bab_s0_tidak_terkendali')->default(false);
            $table->boolean('aks_bab_s1_kadang_terkendali')->default(false);
            $table->boolean('aks_bab_s2_terkendali')->default(false);

            $table->boolean('aks_bak_s0_tidak_terkendali_kateter')->default(false);
            $table->boolean('aks_bak_s1_kadang_1x24jam')->default(false);
            $table->boolean('aks_bak_s2_mandiri')->default(false);

            $table->boolean('aks_diri_s0_butuh_orang_lain')->default(false);
            $table->boolean('aks_diri_s1_mandiri')->default(false);

            $table->boolean('aks_wc_s0_tergantung_lain')->default(false);
            $table->boolean('aks_wc_s1_perlu_beberapa_bisa_sendiri')->default(false);
            $table->boolean('aks_wc_s2_mandiri')->default(false);

            $table->boolean('aks_makan_s0_tidak_mampu')->default(false);
            $table->boolean('aks_makan_s1_perlu_pemotongan')->default(false);
            $table->boolean('aks_makan_s2_mandiri')->default(false);

            $table->boolean('aks_bergerak_s0_tidak_mampu')->default(false);
            $table->boolean('aks_bergerak_s1_butuh_2orang')->default(false);
            $table->boolean('aks_bergerak_s2_butuh_1orang')->default(false);
            $table->boolean('aks_bergerak_s3_mandiri')->default(false);

            $table->boolean('aks_jalan_s0_tidak_mampu')->default(false);
            $table->boolean('aks_jalan_s1_kursi_roda')->default(false);
            $table->boolean('aks_jalan_s2_bantuan_1orang')->default(false);
            $table->boolean('aks_jalan_s3_mandiri')->default(false);

            $table->boolean('aks_pakaian_s0_tergantung_lain')->default(false);
            $table->boolean('aks_pakaian_s1_sebagian_dibantu')->default(false);
            $table->boolean('aks_pakaian_s2_mandiri')->default(false);

            $table->boolean('aks_tangga_s0_tidak_mampu')->default(false);
            $table->boolean('aks_tangga_s1_butuh_bantuan')->default(false);
            $table->boolean('aks_tangga_s2_mandiri')->default(false);

            $table->boolean('aks_mandi_s0_tergantung_lain')->default(false);
            $table->boolean('aks_mandi_s1_mandiri')->default(false);

            // ==================== PERHITUNGAN OTOMATIS AKS ====================
            $table->tinyInteger('aks_total_skor')->storedAs('
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
            ');

            $table->enum('aks_kategori', ['M','R','S','B','T'])->storedAs("
                CASE
                    WHEN aks_total_skor >= 20 THEN 'M'
                    WHEN aks_total_skor >= 12 THEN 'R'
                    WHEN aks_total_skor >= 9  THEN 'S'
                    WHEN aks_total_skor >= 5  THEN 'B'
                    ELSE 'T'
                END
            ");

            $table->boolean('aks_rujuk_otomatis')->storedAs('aks_total_skor < 20');
            $table->text('aks_edukasi')->nullable();
            $table->boolean('aks_rujuk_manual')->default(false);
            $table->text('aks_catatan')->nullable();

            // ==================== SKILAS (15 + 2 tambahan) ====================
            $table->boolean('skil_orientasi_waktu_tempat')->default(false);
            $table->boolean('skil_mengulang_ketiga_kata')->default(false);
            $table->boolean('skil_tes_berdiri_dari_kursi')->default(false);
            $table->boolean('skil_bb_berkurang_3kg_dalam_3bulan')->default(false);
            $table->boolean('skil_hilang_nafsu_makan')->default(false);
            $table->boolean('skil_lla_kurang_21cm')->default(false);
            $table->boolean('skil_masalah_pada_mata')->default(false);
            $table->boolean('skil_tes_melihat')->default(false);
            $table->boolean('skil_tes_bisik')->default(false);
            $table->boolean('skil_perasaan_sedih_tertekan')->default(false);
            $table->boolean('skil_tidak_dapat_dilakukan')->default(false);
            $table->boolean('skil_sedikit_minat_atau_kenikmatan')->default(false);
            $table->boolean('skil_imunisasi_covid')->default(false);

            $table->boolean('skil_rujuk_otomatis')->storedAs('
                skil_orientasi_waktu_tempat OR
                skil_mengulang_ketiga_kata OR
                skil_tes_berdiri_dari_kursi OR
                skil_bb_berkurang_3kg_dalam_3bulan OR
                skil_hilang_nafsu_makan OR
                skil_lla_kurang_21cm OR
                skil_masalah_pada_mata OR
                skil_tes_melihat OR
                skil_tes_bisik OR
                skil_perasaan_sedih_tertekan OR
                skil_tidak_dapat_dilakukan OR
                skil_sedikit_minat_atau_kenikmatan
            ');

            $table->text('skil_edukasi')->nullable();
            $table->boolean('skil_rujuk_manual')->default(false);
            $table->text('skil_catatan')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pemeriksaan_lansia');
    }
};
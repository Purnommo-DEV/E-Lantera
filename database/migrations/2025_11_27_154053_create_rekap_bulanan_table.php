<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE OR REPLACE VIEW rekap_bulanan_dewasa_lansia AS
            SELECT
                DATE_FORMAT(coalesce(d.tanggal_periksa, l.tanggal_periksa), '%Y-%m') AS bulan_tahun,

                -- IMT
                COUNT(CASE WHEN d.kategori_imt = 'Sangat Kurus' THEN 1 END) AS imt_sangat_kurus,
                COUNT(CASE WHEN d.kategori_imt = 'Kurus' THEN 1 END) AS imt_kurus,
                COUNT(CASE WHEN d.kategori_imt = 'Normal' THEN 1 END) AS imt_normal,
                COUNT(CASE WHEN d.kategori_imt = 'Gemuk' THEN 1 END) AS imt_gemuk,
                COUNT(CASE WHEN d.kategori_imt = 'Obesitas' THEN 1 END) AS imt_obesitas,

                -- Lingkar Perut Risiko
                COUNT(CASE WHEN d.lingkar_perut > 90 AND w.jenis_kelamin = 'Laki-laki' THEN 1 END) +
                COUNT(CASE WHEN d.lingkar_perut > 80 AND w.jenis_kelamin = 'Perempuan' THEN 1 END) AS lingkar_perut_risiko,

                -- Tekanan Darah
                COUNT(CASE WHEN d.td_kategori = 'Rendah' THEN 1 END) AS td_rendah,
                COUNT(CASE WHEN d.td_kategori = 'Normal' THEN 1 END) AS td_normal,
                COUNT(CASE WHEN d.td_kategori = 'Tinggi' THEN 1 END) AS td_tinggi,

                -- Gula Darah
                COUNT(CASE WHEN d.gd_kategori = 'Rendah' THEN 1 END) AS gula_rendah,
                COUNT(CASE WHEN d.gd_kategori = 'Normal' THEN 1 END) AS gula_normal,
                COUNT(CASE WHEN d.gd_kategori = 'Tinggi' THEN 1 END) AS gula_tinggi,

                -- PUMA/PPOK
                COUNT(CASE WHEN d.skor_puma > 6 THEN 1 END) AS puma_ppok_positif,

                -- AKS (Lansia)
                COUNT(CASE WHEN l.aks_kategori = 'M' THEN 1 END) AS aks_mandiri,
                COUNT(CASE WHEN l.aks_kategori = 'R' THEN 1 END) AS aks_ringan,
                COUNT(CASE WHEN l.aks_kategori = 'S' THEN 1 END) AS aks_sedang,
                COUNT(CASE WHEN l.aks_kategori = 'B' THEN 1 END) AS aks_berat,
                COUNT(CASE WHEN l.aks_kategori = 'T' THEN 1 END) AS aks_total_tergantung,

                -- SKILAS
                COUNT(CASE WHEN l.skil_orientasi_waktu_tempat = 1 THEN 1 END) AS skilas_kognitif_ya,
                COUNT(CASE WHEN l.skil_tes_berdiri_dari_kursi = 1 THEN 1 END) AS skilas_gerak_ya,
                COUNT(CASE WHEN l.skil_bb_berkurang_3kg_dalam_3bulan = 1 OR l.skil_lla_kurang_21cm = 1 THEN 1 END) AS skilas_malnutrisi_ya,
                COUNT(CASE WHEN l.skil_tes_bisik = 1 THEN 1 END) AS skilas_pendengaran_ya,
                COUNT(CASE WHEN l.skil_masalah_pada_mata = 1 THEN 1 END) AS skilas_penglihatan_ya,
                COUNT(CASE WHEN l.skil_perasaan_sedih_tertekan = 1 OR l.skil_sedikit_minat_atau_kenikmatan = 1 THEN 1 END) AS skilas_depresi_ya,

                -- Imunisasi COVID
                COUNT(CASE WHEN l.skil_imunisasi_covid = 1 THEN 1 END) AS jumlah_lansia_imunisasi_covid,

                -- Edukasi Dewasa
                COUNT(CASE WHEN TRIM(COALESCE(d.edukasi, '')) != '' THEN 1 END) AS jumlah_dewasa_dapat_edukasi,

                -- Total Dirujuk
                COUNT(CASE 
                    WHEN COALESCE(d.rujuk_puskesmas, 0) = 1 
                      OR COALESCE(d.puma_rujuk, 0) = 1 
                      OR COALESCE(d.tbc_rujuk, 0) = 1 
                      OR COALESCE(l.aks_rujuk_otomatis, 0) = 1 
                      OR COALESCE(l.skil_rujuk_otomatis, 0) = 1 
                      OR COALESCE(l.aks_rujuk_manual, 0) = 1 
                      OR COALESCE(l.skil_rujuk_manual, 0) = 1 
                    THEN 1 
                END) AS jumlah_dirujuk

            FROM warga w
            LEFT JOIN pemeriksaan_dewasa_lansia d ON w.id = d.warga_id
            LEFT JOIN pemeriksaan_lansia l ON w.id = l.warga_id 
                AND DATE_FORMAT(l.tanggal_periksa, '%Y-%m') = DATE_FORMAT(d.tanggal_periksa, '%Y-%m')
            GROUP BY DATE_FORMAT(coalesce(d.tanggal_periksa, l.tanggal_periksa), '%Y-%m')
            ORDER BY bulan_tahun DESC
        ");
    }

    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS rekap_bulanan_dewasa_lansia");
    }
};
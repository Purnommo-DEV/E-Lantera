<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 0. LEPAS STATUS GENERATED DAN ENUM YANG MENGGANGGU UPDATE
        Schema::table('pemeriksaan_dewasa_lansia', function (Blueprint $table) {
            // Kolom yang pernah generated → jadikan string biasa dulu
            $table->string('td_kategori', 50)->change();
            $table->string('gd_kategori', 50)->change();
            $table->string('kategori_imt', 50)->change();

            // Kalau skor_puma dulu pernah generated, ini akan "menjinakkan"-nya.
            // Kalau tidak, perintah ini tetap aman.
            $table->integer('skor_puma')->change();

            // PUMA: ubah dulu dari ENUM('Ya','Tidak') → VARCHAR(10) supaya bisa di-UPDATE ke 0/1
            $table->string('puma_napas_pendek', 10)->change();
            $table->string('puma_dahak', 10)->change();
            $table->string('puma_batuk', 10)->change();
            $table->string('puma_spirometri', 10)->change();
        });

        // 1. BERSIHKAN NILAI KOSONG UNTUK TD & GD (masih bentuk teks panjang)
        \DB::statement("
            UPDATE pemeriksaan_dewasa_lansia
            SET
                td_kategori = CASE
                    WHEN td_kategori IS NULL OR td_kategori = '' THEN 'Normal'
                    ELSE td_kategori
                END,
                gd_kategori = CASE
                    WHEN gd_kategori IS NULL OR gd_kategori = '' THEN 'Normal'
                    ELSE gd_kategori
                END
        ");

        // 2. MAPPING TEKS PANJANG → KODE PENDEK
        \DB::statement("
            UPDATE pemeriksaan_dewasa_lansia
            SET
                -- TD & GD: 'Normal' / 'Tinggi' → 'N' / 'T'
                td_kategori = CASE
                    WHEN td_kategori LIKE '%Tinggi%' THEN 'T'
                    ELSE 'N'
                END,
                gd_kategori = CASE
                    WHEN gd_kategori LIKE '%Tinggi%' THEN 'T'
                    ELSE 'N'
                END,

                -- IMT: kategori panjang → kode 2 huruf
                kategori_imt = CASE
                    WHEN kategori_imt IS NULL OR kategori_imt = '' THEN 'N'
                    WHEN kategori_imt LIKE '%Normal%'        THEN 'N'
                    WHEN kategori_imt LIKE '%Sangat Kurus%'  THEN 'SK'
                    WHEN kategori_imt LIKE '%Kurus%'         THEN 'K'
                    WHEN kategori_imt LIKE '%Gemuk%'         THEN 'G'
                    ELSE 'O'
                END,

                -- Mata & telinga: Normal/Gangguan → N/G
                mata_kanan = CASE
                    WHEN mata_kanan = 'Normal'   THEN 'N'
                    WHEN mata_kanan = 'Gangguan' THEN 'G'
                    ELSE NULL
                END,
                mata_kiri = CASE
                    WHEN mata_kiri = 'Normal'   THEN 'N'
                    WHEN mata_kiri = 'Gangguan' THEN 'G'
                    ELSE NULL
                END,
                telinga_kanan = CASE
                    WHEN telinga_kanan = 'Normal'   THEN 'N'
                    WHEN telinga_kanan = 'Gangguan' THEN 'G'
                    ELSE NULL
                END,
                telinga_kiri = CASE
                    WHEN telinga_kiri = 'Normal'   THEN 'N'
                    WHEN telinga_kiri = 'Gangguan' THEN 'G'
                    ELSE NULL
                END
        ");

        // 3. PUMA: KONVERSI 'Ya' / 'Tidak' / NULL → '1' / '0' DI KOLOM VARCHAR
        //    (di sini tipe kolom SUDAH string(10), jadi tidak akan kena enum lagi)
        \DB::statement("
            UPDATE pemeriksaan_dewasa_lansia
            SET
                puma_napas_pendek = CASE
                    WHEN puma_napas_pendek = 'Ya'    THEN '1'
                    WHEN puma_napas_pendek = '1'     THEN '1'
                    ELSE '0'
                END,
                puma_dahak = CASE
                    WHEN puma_dahak = 'Ya'           THEN '1'
                    WHEN puma_dahak = '1'            THEN '1'
                    ELSE '0'
                END,
                puma_batuk = CASE
                    WHEN puma_batuk = 'Ya'           THEN '1'
                    WHEN puma_batuk = '1'            THEN '1'
                    ELSE '0'
                END,
                puma_spirometri = CASE
                    WHEN puma_spirometri = 'Ya'      THEN '1'
                    WHEN puma_spirometri = '1'       THEN '1'
                    ELSE '0'
                END
        ");

        // 4. BARU UBAH TIPE KOLOM KE FORMAT FINAL
        Schema::table('pemeriksaan_dewasa_lansia', function (Blueprint $table) {
            // td_kategori & gd_kategori → CHAR(1) ('N' / 'T')
            $table->char('td_kategori', 1)->default('N')->change();
            $table->char('gd_kategori', 1)->default('N')->change();

            // Mata & telinga → CHAR(1) ('N' / 'G')
            $table->char('mata_kanan', 1)->nullable()->change();
            $table->char('mata_kiri', 1)->nullable()->change();
            $table->char('telinga_kanan', 1)->nullable()->change();
            $table->char('telinga_kiri', 1)->nullable()->change();

            // Merokok → tinyInteger 0/1/2
            $table->tinyInteger('merokok')->default(0)->change();

            // PUMA → tinyInteger 0/1 (dari '0'/'1' string yang sudah dibersihkan)
            $table->tinyInteger('puma_napas_pendek')->default(0)->change();
            $table->tinyInteger('puma_dahak')->default(0)->change();
            $table->tinyInteger('puma_batuk')->default(0)->change();
            $table->tinyInteger('puma_spirometri')->default(0)->change();

            // skor_puma → tinyInteger 0–10
            $table->tinyInteger('skor_puma')->default(0)->change();

            // kategori_imt → CHAR(2): N, SK, K, G, O
            $table->char('kategori_imt', 2)->default('N')->change();
        });

        // 5. DROP KOLOM-KOLOM PUMA LAMA YANG TIDAK DIPAKAI LAGI
        Schema::table('pemeriksaan_dewasa_lansia', function (Blueprint $table) {
            if (Schema::hasColumn('pemeriksaan_dewasa_lansia', 'puma_mengi')) {
                $table->dropColumn(['puma_mengi', 'puma_dokter', 'puma_flu', 'puma_alat']);
            }
            if (Schema::hasColumn('pemeriksaan_dewasa_lansia', 'puma_rujuk')) {
                $table->dropColumn('puma_rujuk');
            }
        });
    }

    public function down()
    {
        // Kalau rollback, kembalikan ke bentuk lama (tidak perlu sempurna)
        Schema::table('pemeriksaan_dewasa_lansia', function (Blueprint $table) {
            $table->string('td_kategori', 10)->default('Normal')->change();
            $table->string('gd_kategori', 10)->default('Normal')->change();
            $table->string('mata_kanan', 15)->nullable()->change();
            $table->string('mata_kiri', 15)->nullable()->change();
            $table->string('telinga_kanan', 15)->nullable()->change();
            $table->string('telinga_kiri', 15)->nullable()->change();
            $table->enum('merokok', ['Ya','Tidak'])->default('Tidak')->change();
        });
    }
};
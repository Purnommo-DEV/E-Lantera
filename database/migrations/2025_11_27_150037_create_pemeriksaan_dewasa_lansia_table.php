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
        Schema::create('pemeriksaan_dewasa_lansia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warga_id')->constrained('warga')->cascadeOnDelete();
            $table->date('tanggal_periksa');
            $table->decimal('berat_badan', 5, 1);
            $table->decimal('tinggi_badan', 5, 1);
            $table->decimal('imt', 5, 2);
            $table->string('kategori_imt', 20);
            $table->tinyInteger('lingkar_perut');
            $table->tinyInteger('lingkar_lengan_atas');
            $table->smallInteger('sistole');
            $table->smallInteger('diastole');
            $table->string('td_kategori', 10);
            $table->smallInteger('gula_darah');
            $table->string('gd_kategori', 10);
            $table->string('mata_kanan', 15)->nullable();
            $table->string('mata_kiri', 15)->nullable();
            $table->string('telinga_kanan', 15)->nullable();
            $table->string('telinga_kiri', 15)->nullable();
            $table->enum('merokok', ['Ya','Tidak']);
            // PUMA 8
            $table->enum('puma_napas_pendek', ['Ya','Tidak'])->default('Tidak');
            $table->enum('puma_dahak', ['Ya','Tidak'])->default('Tidak');
            $table->enum('puma_batuk', ['Ya','Tidak'])->default('Tidak');
            $table->enum('puma_mengi', ['Ya','Tidak'])->default('Tidak');
            $table->enum('puma_dokter', ['Ya','Tidak'])->default('Tidak');
            $table->enum('puma_flu', ['Ya','Tidak'])->default('Tidak');
            $table->enum('puma_spirometri', ['Ya','Tidak'])->default('Tidak');
            $table->enum('puma_alat', ['Ya','Tidak'])->default('Tidak');
            $table->tinyInteger('skor_puma');
            $table->boolean('puma_rujuk');
            // TBC
            $table->enum('tbc_batuk', ['Ya','Tidak'])->default('Tidak');
            $table->enum('tbc_demam', ['Ya','Tidak'])->default('Tidak');
            $table->enum('tbc_bb_turun', ['Ya','Tidak'])->default('Tidak');
            $table->enum('tbc_kontak', ['Ya','Tidak'])->default('Tidak');
            $table->boolean('tbc_rujuk');
            $table->tinyInteger('usia');
            $table->enum('wawancara_kontrasepsi', ['Ya','Tidak']);
            $table->string('jenis_kontrasepsi', 50)->nullable();
            $table->text('edukasi')->nullable();
            $table->boolean('rujuk_puskesmas')->default(false);
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pemeriksaan_dewasa_lansia');
    }
};

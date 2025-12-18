<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pemeriksaan_dewasa_lansia', function (Blueprint $table) {

            // === IMT ===
            $table->decimal('imt', 5, 2)->change();
            $table->string('kategori_imt', 20)->change();

            // === TD & GULA ===
            $table->string('td_kategori', 10)->change();
            $table->string('gd_kategori', 10)->change();

            // === PUMA ===
            $table->tinyInteger('skor_puma')->change();
            // === TBC ===
            $table->boolean('tbc_rujuk')->change();
        });
    }

    public function down(): void
    {
        // rollback TIDAK direkomendasikan karena stored column
        // biasanya perlu DROP & CREATE ulang
    }
};

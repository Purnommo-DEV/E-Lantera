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
        Schema::table('pemeriksaan_dewasa_lansia', function (Blueprint $table) {
            if (Schema::hasColumn('pemeriksaan_dewasa_lansia', 'tbc_rujuk')) {
                $table->dropColumn('tbc_rujuk');
            }
        });

        Schema::table('pemeriksaan_dewasa_lansia', function (Blueprint $table) {
            $table->boolean('tbc_rujuk')
                  ->default(false)
                  ->after('tbc_kontak');
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

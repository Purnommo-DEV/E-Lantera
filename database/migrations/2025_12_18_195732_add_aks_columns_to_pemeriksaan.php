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

            $table->tinyInteger('aks_total_skor')
                  ->default(0)
                  ->after('aks_edukasi');

            $table->enum('aks_kategori', ['M','R','S','B','T'])
                  ->nullable()
                  ->after('aks_total_skor');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pemeriksaan_lansia', function (Blueprint $table) {
            //
        });
    }
};

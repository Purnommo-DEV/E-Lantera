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

            $table->tinyInteger('aks_rujuk_otomatis')
                  ->default(0)
                  ->after('aks_catatan');
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

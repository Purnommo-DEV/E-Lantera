<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warga', function (Blueprint $table) {
            $table->char('nik', 16)->nullable()->change();
            $table->date('tanggal_lahir')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('warga', function (Blueprint $table) {
            $table->char('nik', 16)->nullable(false)->unique()->change();
            $table->date('tanggal_lahir')->nullable(false)->change();
        });
    }
};

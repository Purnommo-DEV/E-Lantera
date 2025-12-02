<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warga', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100);
            $table->char('nik', 16)->unique();
            $table->date('tanggal_lahir');
            $table->enum('jenis_kelamin', ['Laki-laki', 'Perempuan']);
            $table->string('alamat', 255);
            $table->string('no_hp', 15)->nullable();
            $table->enum('status_nikah', ['Menikah', 'Tidak Menikah']);
            $table->string('pekerjaan', 100)->nullable();
            $table->string('dusun', 50);
            $table->char('rt', 3);
            $table->char('rw', 3);
            $table->string('desa', 50)->default('CIPADU JAYA');
            $table->string('kecamatan', 50)->default('LARANGAN');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warga');
    }
};
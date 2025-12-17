<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warga extends Model
{
    use HasFactory;

    protected $table = 'warga';

    protected $guarded = ['id'];

    protected $appends = ['umur'];

    protected $casts = [
        'tanggal_lahir' => 'date'
    ];

    // Akses umur otomatis
    public function getUmurAttribute()
    {
        $tahun = $this->tanggal_lahir->diffInYears(now());
        $bulan = $this->tanggal_lahir->diffInMonths(now()) % 12;
        return "$tahun tahun $bulan bulan";
    }

    // Untuk tampilan jenis kelamin
    public function getJkAttribute()
    {
        return $this->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan';
    }

    // Semua riwayat pemeriksaan
    public function pemeriksaanDewasaLansiaAll()
    {
        return $this->hasMany(PemeriksaanDewasaLansia::class, 'warga_id')
                    ->latest('tanggal_periksa');
    }

    // SEMUA WARGA
    public function pemeriksaanDewasaLansia()
    {
        return $this->hasMany(PemeriksaanDewasaLansia::class, 'warga_id')
                    ->latest('tanggal_periksa');
    }

    // Hanya 1 pemeriksaan terakhir (recommended)
    public function pemeriksaanDewasaLansiaTerakhir()
    {
        return $this->hasOne(PemeriksaanDewasaLansia::class, 'warga_id')
                    ->latestOfMany('tanggal_periksa'); // ⬅ ini yang bikin "terakhir per warga"
    }

    public function riwayatPemeriksaanLansia()
    {
        return $this->hasMany(PemeriksaanLansia::class)->orderByDesc('tanggal_periksa');
    }

    public function pemeriksaanLansiaTerakhir()
    {
        return $this->hasOne(PemeriksaanLansia::class)->latest('tanggal_periksa');
    }
    
    public function pemeriksaanLansiaAll()
    {
        return $this->hasMany(PemeriksaanLansia::class, 'warga_id')
                    ->orderBy('tanggal_periksa', 'desc');
    }


}
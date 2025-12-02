<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PemeriksaanLansia extends Model
{
    protected $table = 'pemeriksaan_lansia';
    protected $guarded = ['id'];

    public function warga(): BelongsTo
    {
        return $this->belongsTo(Warga::class);
    }

    public function getAksKategoriTextAttribute()
    {
        return match ($this->aks_kategori) {
            'M' => 'Mandiri',
            'R' => 'Risiko Ringan',
            'S' => 'Sedang',
            'B' => 'Berat',
            'T' => 'Total',
            default => '-',
        };
    }

    public function getAksPerluRujukAttribute()
    {
        return $this->aks_rujuk_otomatis || $this->aks_rujuk_manual;
    }

    public function getSkilasPerluRujukAttribute()
    {
        return $this->skil_rujuk_otomatis || $this->skil_rujuk_manual;
    }
}
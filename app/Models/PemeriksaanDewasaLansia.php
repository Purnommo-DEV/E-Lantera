<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class PemeriksaanDewasaLansia extends Model
{
    protected $table = 'pemeriksaan_dewasa_lansia';

    protected $fillable = [
        'warga_id', 'tanggal_periksa', 'berat_badan', 'tinggi_badan', 'lingkar_perut',
        'lingkar_lengan_atas', 'sistole', 'diastole', 'gula_darah', 'mata_kanan',
        'mata_kiri', 'telinga_kanan', 'telinga_kiri', 'merokok',
        'puma_napas_pendek', 'puma_dahak', 'puma_batuk', 'puma_spirometri',
        'skor_puma', 'tbc_batuk', 'tbc_demam', 'tbc_bb_turun', 'tbc_kontak',
        'wawancara_kontrasepsi', 'jenis_kontrasepsi', 'edukasi',
        'rujuk_puskesmas', 'catatan', 'usia'
    ];

    protected $casts = [
        'tanggal_periksa' => 'date',
        'berat_badan'     => 'decimal:1',
        'tinggi_badan'    => 'decimal:1',
        'imt'             => 'decimal:2',
        'tbc_rujuk'       => 'boolean',
        'rujuk_puskesmas' => 'boolean',
    ];

    protected $appends = ['imt_badge_html', 'td_badge_html']; // penting!

    // Relasi ke Warga
    public function warga(): BelongsTo
    {
        return $this->belongsTo(Warga::class);
    }

    // Accessor biar lebih gampang dipakai di Blade
    public function getImtFormattedAttribute()
    {
        return number_format($this->imt, 2);
    }

    public function getTdDisplayAttribute()
    {
        return "{$this->sistole}/{$this->diastole} mmHg";
    }

    protected function imtBadgeHtml(): Attribute
    {
        return Attribute::get(function () {
            if (!$this->kategori_imt) {
                return null;
            }

            $class = match ($this->kategori_imt) {
                'N'        => 'success',
                'O'        => 'error',
                'SK', 'K',
                'G'        => 'warning',
                default    => 'secondary',
            };

            $label = match ($this->kategori_imt) {
                'SK' => 'Sangat Kurus',
                'K'  => 'Kurus',
                'N'  => 'Normal',
                'G'  => 'Gemuk',
                'O'  => 'Obesitas',
                default => '-',
            };

            return sprintf(
                '<span class="badge badge-lg badge-%s">%s</span>',
                $class,
                $label
            );
        });
    }

    protected function tdBadgeHtml(): Attribute
    {
        return Attribute::get(function () {
            if (!$this->td_kategori) {
                return null;
            }

            $class = match ($this->td_kategori) {
                'R'     => 'warning', // Rendah → kuning
                'N'     => 'success', // Normal → hijau
                'T'     => 'error',   // Tinggi → merah
                default => 'secondary',
            };

            $label = match ($this->td_kategori) {
                'R'     => 'Rendah',
                'N'     => 'Normal',
                'T'     => 'Tinggi',
                default => '-',
            };

            return sprintf(
                '<span class="badge badge-%s">%s</span>',
                $class,
                $label
            );
        });
    }

}
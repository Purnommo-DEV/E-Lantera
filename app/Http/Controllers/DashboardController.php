<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Warga;
use App\Models\PemeriksaanDewasaLansia;
use App\Models\PemeriksaanLansia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Periode: bulan ini
        $start = Carbon::now()->startOfMonth()->toDateString();
        $end   = Carbon::now()->endOfMonth()->toDateString();

        // Total umum
        $totalUsers  = class_exists(\App\Models\User::class) ? User::count() : 0;
        // Role count (jika spatie/roles ada)
        $totalRoles = 0;
        if (class_exists(\Spatie\Permission\Models\Role::class)) {
            $totalRoles = \Spatie\Permission\Models\Role::count();
        }

        // Data warga
        $totalWarga = Warga::count();

        // Pemeriksaan bulan ini
        $dewasaThisMonth = PemeriksaanDewasaLansia::whereBetween('tanggal_periksa', [$start, $end])->count();
        $lansiaThisMonth = PemeriksaanLansia::whereBetween('tanggal_periksa', [$start, $end])->count();

        // Jumlah rujukan bulan ini (dewasa + lansia)
        $rujukDewasa = PemeriksaanDewasaLansia::whereBetween('tanggal_periksa', [$start, $end])
            ->where(function($q){
                $q->where('rujuk_puskesmas', 1)
                  ->orWhere('tbc_rujuk', 1);
            })->count();

        $rujukLansia = PemeriksaanLansia::whereBetween('tanggal_periksa', [$start, $end])
            ->where(function($q){
                $q->where('aks_rujuk_otomatis', 1)
                  ->orWhere('aks_rujuk_manual', 1)
                  ->orWhere('skil_rujuk_otomatis', 1)
                  ->orWhere('skil_rujuk_manual', 1);
            })->count();

        $totalRujukanThisMonth = $rujukDewasa + $rujukLansia;

        // Aktivitas terakhir: ambil pemeriksaan terbaru dari kedua tabel
        $lastDewasa = PemeriksaanDewasaLansia::latest('updated_at')->first();
        $lastLansia = PemeriksaanLansia::latest('updated_at')->first();

        $lastActivity = null;
        if ($lastDewasa && $lastLansia) {
            $lastActivity = $lastDewasa->updated_at > $lastLansia->updated_at ? [
                'type'=>'Dewasa','time'=>$lastDewasa->updated_at,'warga_id'=>$lastDewasa->warga_id,'id'=>$lastDewasa->id
            ] : [
                'type'=>'Lansia','time'=>$lastLansia->updated_at,'warga_id'=>$lastLansia->warga_id,'id'=>$lastLansia->id
            ];
        } elseif ($lastDewasa) {
            $lastActivity = ['type'=>'Dewasa','time'=>$lastDewasa->updated_at,'warga_id'=>$lastDewasa->warga_id,'id'=>$lastDewasa->id];
        } elseif ($lastLansia) {
            $lastActivity = ['type'=>'Lansia','time'=>$lastLansia->updated_at,'warga_id'=>$lastLansia->warga_id,'id'=>$lastLansia->id];
        }

        // ringkasan bulanan 12 bulan terakhir (opsional, untuk grafik di masa depan)
        $months = collect();
        for ($i = 0; $i < 12; $i++) {
            $dt = Carbon::now()->subMonths($i);
            $mStart = $dt->copy()->startOfMonth()->toDateString();
            $mEnd   = $dt->copy()->endOfMonth()->toDateString();

            $months->push([
                'label' => $dt->translatedFormat('F Y'),
                'dewasa' => PemeriksaanDewasaLansia::whereBetween('tanggal_periksa', [$mStart, $mEnd])->count(),
                'lansia' => PemeriksaanLansia::whereBetween('tanggal_periksa', [$mStart, $mEnd])->count(),
            ]);
        }

        return view('page.dashboard', compact(
            'totalUsers','totalRoles','totalWarga',
            'dewasaThisMonth','lansiaThisMonth',
            'totalRujukanThisMonth','lastActivity','months'
        ));
    }
}

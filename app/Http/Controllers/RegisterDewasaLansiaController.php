<?php

namespace App\Http\Controllers;

use App\Models\Warga;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class RegisterDewasaLansiaController extends Controller
{
    public function index(Request $request)
    {
        $tahun = $request->tahun ?? now()->year;

        // Ambil semua warga
        $warga = Warga::orderBy('nama')->get();

        $warga->transform(function ($w) use ($tahun) {

            // ===============================
            // UMUR & STATUS
            // ===============================
            $w->umur = $w->tanggal_lahir
                ? Carbon::parse($w->tanggal_lahir)->age
                : null;

            // ===============================
            // KEHADIRAN JAN–DES
            // ===============================
            $hadir = [];

            for ($bulan = 1; $bulan <= 12; $bulan++) {

                $hadirDewasa = DB::table('pemeriksaan_dewasa_lansia')
                    ->where('warga_id', $w->id)
                    ->whereYear('tanggal_periksa', $tahun)
                    ->whereMonth('tanggal_periksa', $bulan)
                    ->exists();

                $hadirLansia = DB::table('pemeriksaan_lansia')
                    ->where('warga_id', $w->id)
                    ->whereYear('tanggal_periksa', $tahun)
                    ->whereMonth('tanggal_periksa', $bulan)
                    ->exists();

                // Hadir jika ADA salah satu
                $hadir[$bulan] = $hadirDewasa || $hadirLansia;
            }

            $w->hadir = $hadir;

            return $w;
        });

        return view('page.register-dewasa-lansia.index', compact('warga', 'tahun'));
    }

    public function exportExcel(Request $request)
    {
        $tahun = $request->tahun ?? now()->year;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // ===============================
        // HEADER
        // ===============================
        $sheet->setCellValue('A1', 'REGISTER DEWASA & LANSIA');
        $sheet->setCellValue('A2', "TAHUN {$tahun}");

        $sheet->mergeCells('A1:Q1');
        $sheet->mergeCells('A2:Q2');

        // ===============================
        // KOLOM
        // ===============================
        $headers = [
            'No','Nama','JK','Tanggal Lahir','Umur',
            'Jan','Feb','Mar','Apr','Mei','Jun',
            'Jul','Agu','Sep','Okt','Nov','Des'
        ];

        $row = 4;
        $col = 'A';

        foreach ($headers as $h) {
            $sheet->setCellValue($col.$row, $h);
            $col++;
        }

        // ===============================
        // DATA
        // ===============================
        $warga = Warga::orderBy('nama')->get();
        $row++;

        foreach ($warga as $i => $w) {

            $umur = $w->tanggal_lahir
                ? Carbon::parse($w->tanggal_lahir)->age
                : '';

            $sheet->fromArray([
                $i + 1,
                $w->nama,
                $w->jenis_kelamin[0],
                optional($w->tanggal_lahir)->format('d-m-Y'),
                $umur
            ], null, 'A'.$row);

            // Kolom bulan
            for ($bulan = 1; $bulan <= 12; $bulan++) {

                $hadir = DB::table('pemeriksaan_dewasa_lansia')
                    ->where('warga_id', $w->id)
                    ->whereYear('tanggal_periksa', $tahun)
                    ->whereMonth('tanggal_periksa', $bulan)
                    ->exists()
                 || DB::table('pemeriksaan_lansia')
                    ->where('warga_id', $w->id)
                    ->whereYear('tanggal_periksa', $tahun)
                    ->whereMonth('tanggal_periksa', $bulan)
                    ->exists();

                if ($hadir) {
                    $sheet->setCellValueByColumnAndRow(5 + $bulan, $row, 'V');
                }
            }

            $row++;
        }

        // ===============================
        // OUTPUT
        // ===============================
        $writer = new Xlsx($spreadsheet);
        $fileName = "register-dewasa-lansia-{$tahun}.xlsx";

        return response()->streamDownload(
            fn () => $writer->save('php://output'),
            $fileName
        );
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\PemeriksaanDewasaLansia;
use App\Models\PemeriksaanLansia;
use Illuminate\Http\Request;
use App\Models\Warga;
use Carbon\Carbon;
use App\Exports\RekapBulananExport;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class RekapController extends Controller
{
    public function index()
    {
        return view('page.rekap.index');
    }

    public function bulanan()
    {
        return view('page.rekap.index');
    }

    public function dataBulanan()
    {
        $now = now();
        $data = [];

        // 12 bulan terakhir + bulan ini
        for ($i = 11; $i >= 0; $i--) {
            $date = $now->copy()->startOfMonth()->subMonths($i);
            $tahun = $date->year;
            $bulan = $date->month;

            $jmlDewasa = PemeriksaanDewasaLansia::whereYear('tanggal_periksa', $tahun)
                ->whereMonth('tanggal_periksa', $bulan)
                ->count();

            $jmlLansia = PemeriksaanLansia::whereYear('tanggal_periksa', $tahun)
                ->whereMonth('tanggal_periksa', $bulan)
                ->count();

            $total = $jmlDewasa + $jmlLansia;

            $data[] = [
                'bulan'       => $date->translatedFormat('F Y'),
                'tahun'       => $tahun,
                'bulan_num'   => $bulan,
                'dewasa'      => $jmlDewasa,
                'lansia'      => $jmlLansia,
                'total'       => $total,
                'badge'       => $total > 0
                    ? '<span class="badge badge-lg badge-success text-white">'.$total.' orang</span>'
                    : '<span class="text-gray-400">—</span>',
            ];
        }

        return response()->json(['data' => $data]);
    }

    public function detailBulanan($tahun, $bulan)
    {
        $start = Carbon::create($tahun, $bulan, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $dewasa = PemeriksaanDewasaLansia::with('warga')
            ->whereBetween('tanggal_periksa', [$start, $end])
            ->orderBy('tanggal_periksa', 'desc')
            ->get();

        $lansia = PemeriksaanLansia::with('warga')
            ->whereBetween('tanggal_periksa', [$start, $end])
            ->orderBy('tanggal_periksa', 'desc')
            ->get();

        $lansia->each(function ($l) {
            $l->skilas_positif = collect($l->only([
                'skil_orientasi_waktu_tempat',
                'skil_mengulang_ketiga_kata',
                'skil_tes_berdiri_dari_kursi',
                'skil_bb_berkurang_3kg_dalam_3bulan',
                'skil_hilang_nafsu_makan',
                'skil_lla_kurang_21cm',
                'skil_masalah_pada_mata',
                'skil_tes_melihat',
                'skil_tes_bisik',
                'skil_perasaan_sedih_tertekan',
                'skil_tidak_dapat_dilakukan',
                'skil_sedikit_minat_atau_kenikmatan',
            ]))->sum() > 0;
        });

        $html = view('page.rekap.detail', compact('dewasa', 'lansia', 'start'))->render();

        return response($html)->header('Content-Type', 'text/html');
    }

    public function exportExcel(Request $request)
    {
        // Hindari timeout
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        // Dipanggil dengan ?tahun=YYYY&bulan=MM
        $request->validate([
            'tahun' => 'required|integer',
            'bulan' => 'required|integer|between:1,12',
        ]);

        $tahun = (int) $request->tahun;
        $bulan = (int) $request->bulan;

        $start = Carbon::create($tahun, $bulan, 1)->startOfMonth();
        $end   = (clone $start)->endOfMonth();
        $bulanNama = $start->translatedFormat('F Y');

        // ================== AGREGASI DATA (HANYA 1 QUERY) ==================
        // IMT, Lingkar Perut, TD, GD, PUMA dari pemeriksaan_dewasa_lansia
        $agg = PemeriksaanDewasaLansia::query()
            ->leftJoin('warga', 'warga.id', '=', 'pemeriksaan_dewasa_lansia.warga_id')
            ->whereBetween('tanggal_periksa', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('COUNT(*) as total_pemeriksaan')

            // IMT (kategori_imt: SK, K, N, G, O)
            ->selectRaw("SUM(CASE WHEN kategori_imt = 'SK' THEN 1 ELSE 0 END) as imt_sangat_kurus")
            ->selectRaw("SUM(CASE WHEN kategori_imt = 'K'  THEN 1 ELSE 0 END) as imt_kurus")
            ->selectRaw("SUM(CASE WHEN kategori_imt = 'N'  THEN 1 ELSE 0 END) as imt_normal")
            ->selectRaw("SUM(CASE WHEN kategori_imt = 'G'  THEN 1 ELSE 0 END) as imt_gemuk")
            ->selectRaw("SUM(CASE WHEN kategori_imt = 'O'  THEN 1 ELSE 0 END) as imt_obesitas")

            // Lingkar Perut (butuh jenis_kelamin dari warga)
            ->selectRaw("
                SUM(
                    CASE 
                        WHEN (warga.jenis_kelamin IN ('L','Laki-laki','Laki')) 
                             AND lingkar_perut > 90 
                        THEN 1 ELSE 0 
                    END
                ) as lp_laki
            ")
            ->selectRaw("
                SUM(
                    CASE 
                        WHEN (warga.jenis_kelamin IN ('P','Perempuan')) 
                             AND lingkar_perut > 80 
                        THEN 1 ELSE 0 
                    END
                ) as lp_perempuan
            ")

            // Tekanan Darah (td_kategori: N/T; rendah masih 0 untuk saat ini)
            ->selectRaw("SUM(CASE WHEN td_kategori = 'N' THEN 1 ELSE 0 END) as td_normal")
            ->selectRaw("SUM(CASE WHEN td_kategori = 'T' THEN 1 ELSE 0 END) as td_tinggi")

            // Gula Darah (gd_kategori: N/T)
            ->selectRaw("SUM(CASE WHEN gd_kategori = 'N' THEN 1 ELSE 0 END) as gd_normal")
            ->selectRaw("SUM(CASE WHEN gd_kategori = 'T' THEN 1 ELSE 0 END) as gd_tinggi")

            // PUMA (skor_puma tinyint, default 0)
            ->selectRaw("SUM(CASE WHEN skor_puma > 0 AND skor_puma <= 6 THEN 1 ELSE 0 END) as puma_normal")
            ->selectRaw("SUM(CASE WHEN skor_puma > 6 THEN 1 ELSE 0 END) as puma_tinggi")

            ->first();

        if (!$agg || $agg->total_pemeriksaan == 0) {
            return back()->with('warning', 'Tidak ada data pemeriksaan pada bulan ini.');
        }

        // ================== BUAT EXCEL SESUAI TEMPLATE TAHAP 1 ==================
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header paling atas
        $sheet->mergeCells('A1:P1');
        $sheet->setCellValue('A1', 'Hasil Penimbangan / Pengukuran Usia Dewasa dan Lansia');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Kolom "Bulan dan Tahun"
        $sheet->mergeCells('A2:A4');
        $sheet->setCellValue('A2', 'Bulan dan Tahun');
        $sheet->getStyle('A2:A4')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle('A2:A4')->getFont()->setBold(true);

        // Baris "Usia Dewasa dan Lansia"
        $sheet->mergeCells('B2:P2');
        $sheet->setCellValue('B2', 'Usia Dewasa dan Lansia');
        $sheet->getStyle('B2:P2')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('B2:P2')->getFont()->setBold(true);

        // Baris kategori besar (IMT, Lingkar Perut, Tekanan Darah, Gula Darah, PUMA)
        $sheet->mergeCells('B3:F3')->setCellValue('B3', 'IMT');
        $sheet->mergeCells('G3:H3')->setCellValue('G3', 'Lingkar Perut');
        $sheet->mergeCells('I3:K3')->setCellValue('I3', 'Tekanan Darah');
        $sheet->mergeCells('L3:N3')->setCellValue('L3', 'Gula Darah');
        $sheet->mergeCells('O3:P3')->setCellValue('O3', "Skrining PUMA/PPOK\n(Usia dewasa ≥40 tahun)");

        $sheet->getStyle('B3:P3')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle('B3:P3')->getFont()->setBold(true);

        // Baris label kolom kecil sesuai screenshot (1..16)
        $headers = [
            'B4' => 'Sangat Kurus',
            'C4' => 'Kurus',
            'D4' => 'Normal',
            'E4' => 'Gemuk',
            'F4' => 'Obesitas',
            'G4' => "Laki-laki\n>90 Cm",
            'H4' => "Perempuan\n>80 Cm",
            'I4' => 'Rendah',
            'J4' => 'Normal',
            'K4' => 'Tinggi',
            'L4' => 'Rendah',
            'M4' => 'Normal',
            'N4' => 'Tinggi',
            'O4' => "Normal\n< 6",
            'P4' => "Tinggi\n> 6",
        ];

        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }

        $sheet->getStyle('B4:P4')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle('B4:P4')->getFont()->setBold(true);

        // ================== ISI BARIS DATA UNTUK BULAN TERPILIH ==================
        $dataRow = 5;

        // Kolom A: Bulan dan Tahun
        $sheet->setCellValue("A{$dataRow}", $bulanNama);

        // IMT
        $sheet->setCellValue("B{$dataRow}", (int)$agg->imt_sangat_kurus);
        $sheet->setCellValue("C{$dataRow}", (int)$agg->imt_kurus);
        $sheet->setCellValue("D{$dataRow}", (int)$agg->imt_normal);
        $sheet->setCellValue("E{$dataRow}", (int)$agg->imt_gemuk);
        $sheet->setCellValue("F{$dataRow}", (int)$agg->imt_obesitas);

        // Lingkar Perut
        $sheet->setCellValue("G{$dataRow}", (int)$agg->lp_laki);
        $sheet->setCellValue("H{$dataRow}", (int)$agg->lp_perempuan);

        // Tekanan Darah (rendah masih 0 untuk saat ini)
        $sheet->setCellValue("I{$dataRow}", 0); // kalau nanti mau hitung "rendah" tinggal diubah query
        $sheet->setCellValue("J{$dataRow}", (int)$agg->td_normal);
        $sheet->setCellValue("K{$dataRow}", (int)$agg->td_tinggi);

        // Gula Darah
        $sheet->setCellValue("L{$dataRow}", 0);
        $sheet->setCellValue("M{$dataRow}", (int)$agg->gd_normal);
        $sheet->setCellValue("N{$dataRow}", (int)$agg->gd_tinggi);

        // PUMA
        $sheet->setCellValue("O{$dataRow}", (int)$agg->puma_normal);
        $sheet->setCellValue("P{$dataRow}", (int)$agg->puma_tinggi);

        // ================== STYLING TABEL ==================
        // Border seluruh area
        $lastRow = $dataRow; // sementara cuma 1 baris data
        $sheet->getStyle("A2:P{$lastRow}")
            ->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // Lebar kolom
        $sheet->getColumnDimension('A')->setWidth(18);
        foreach (range('B', 'P') as $col) {
            $sheet->getColumnDimension($col)->setWidth(12);
        }

        // Tinggi baris untuk header multi-baris
        $sheet->getRowDimension(3)->setRowHeight(30);
        $sheet->getRowDimension(4)->setRowHeight(45);
        $sheet->getRowDimension($dataRow)->setRowHeight(20);

        // ================== DOWNLOAD ==================
        $filename = "Rekap_Usia_Dewasa_Lansia_{$bulanNama}.xlsx";
        $writer = new Xlsx($spreadsheet);

        if (ob_get_length()) {
            @ob_end_clean();
        }

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    public function exportKemenkesTahunan(Request $request)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $request->validate([
            'tahun' => 'required|integer',
        ]);

        $tahun = (int) $request->tahun;

        // --- ukuran font header (boleh kamu sesuaikan) ---
        $fontSizeDepanBelakang = 14;
        $fontSizeHeaderUtama   = 14;
        $fontSizeProfil        = 12;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // =====================================================================
        // 1. HEADER UTAMA
        // =====================================================================
        // LOGO
        $logo = new Drawing();
        $logo->setName('Logo');
        $logo->setDescription('Logo Posyandu');
        $logo->setPath(public_path('posyandu.png'));

        $logo->setHeight(60);              // 🔥 JANGAN kegedean
        $logo->setResizeProportional(true);

        $logo->setCoordinates("N2");
        $logo->setOffsetX(3);
        $logo->setOffsetY(-2);             // 🔥 sejajar teks

        $logo->setWorksheet($sheet);

        // penting: kolom jangan lebar
        $sheet->getColumnDimension('K')->setWidth(6);

        $sheet->setCellValue('A2', 'REKAPITULASI HASIL PEMERIKSAAN USIA DEWASA DAN USIA LANJUT (≥19 Tahun)');
        $sheet->mergeCells('A2:AJ2');
        $sheet->getStyle('A2:AJ2')->getFont()->setSize($fontSizeHeaderUtama)->setBold(true);
        $sheet->getStyle('A2:AJ2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A3', 'POSYANDU TAMAN CIPULIR ESTATE');
        $sheet->mergeCells('A3:AJ3');
        $sheet->getStyle('A3:AJ3')->getFont()->setSize($fontSizeHeaderUtama)->setBold(true);
        $sheet->getStyle('A3:AJ3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Label identitas
        $labelsAKS = [
            'A5' => 'Dusun/RT/RW',
            'A6' => 'Desa/Kelurahan/Nagari',
            'A7' => 'Kecamatan',
        ];

        foreach ($labelsAKS as $cell => $text) {
            $row = preg_replace('/\D/', '', $cell);

            $sheet->mergeCells("A{$row}:B{$row}");
            $sheet->setCellValue("A{$row}", $text);

            $sheet->getStyle("A{$row}:B{$row}")->getFont()
                ->setSize($fontSizeProfil)
                ->setBold(true);

            $sheet->getStyle("A{$row}:B{$row}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);
        }

        // Titik dua di kolom C
        foreach (['C5','C6','C7'] as $cell) {
            $sheet->setCellValue($cell, ':');
            $sheet->getStyle($cell)->getFont()->setSize(12);
            $sheet->getStyle($cell)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                ->setVertical(Alignment::VERTICAL_CENTER);
        }

        // Isi identitas di kolom D–F
        $dataIdentitas = [
            5 => "RW 08",
            6 => "Cipadu Jaya",
            7 => "Larangan",
        ];

        foreach ($dataIdentitas as $row => $value) {
            $sheet->mergeCells("D{$row}:F{$row}");
            $sheet->setCellValue("D{$row}", $value ?? '-');

            $sheet->getStyle("D{$row}:F{$row}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);
        }

        $row9 = 9;

        $sheet->mergeCells("B{$row9}:AG{$row9}");
        $sheet->setCellValue("B{$row9}", 'Hasil Penimbangan / Pengukuran / Pemeriksaan ');
        $sheet->getStyle("B{$row9}:AG{$row9}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle("B{$row9}:AG{$row9}")->getFont()->setBold(true);

        $sheet->getStyle("B{$row9}:AG{$row9}")
            ->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // =====================================================================
        // 2. TABEL REKAP FORMAT KEMENKES (DITURUNKAN KE BAWAH)
        // =====================================================================
        // Kita mulai tabel di baris 11 supaya tidak nabrak header identitas
        $row10 = $row9 + 1;

        // Kolom "Bulan dan Tahun"
        $sheet->mergeCells("A{$row9}:A". $row10 + 3); // A9:A12
        $sheet->setCellValue("A{$row9}", 'Bulan dan Tahun');
        $sheet->getStyle("A{$row9}:A". $row10 + 3)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle("A{$row9}:A". $row10 + 3)->getFont()->setBold(true);
        $sheet->getStyle("A{$row9}:A". $row10 + 3)
            ->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // Baris 11: kelompok besar
        $sheet->mergeCells("B{$row10}:P{$row10}");
        $sheet->setCellValue("B{$row10}", 'Usia Dewasa dan Lansia');
        $sheet->getStyle("B{$row10}:P{$row10}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("B{$row10}:P{$row10}")->getFont()->setBold(true);

        $sheet->mergeCells("Q{$row10}:AG{$row10}");
        $sheet->setCellValue("Q{$row10}", 'Lansia');
        $sheet->getStyle("Q{$row10}:AG{$row10}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("Q{$row10}:AG{$row10}")->getFont()->setBold(true);

        // Baris 11: kategori besar detail
        $row11 = $row10 + 1;

        $sheet->mergeCells("B{$row11}:F{$row11}")->setCellValue("B{$row11}", 'IMT');
        $sheet->mergeCells("G{$row11}:H{$row11}")->setCellValue("G{$row11}", 'Lingkar Perut');
        $sheet->mergeCells("I{$row11}:K{$row11}")->setCellValue("I{$row11}", 'Tekanan Darah');
        $sheet->mergeCells("L{$row11}:N{$row11}")->setCellValue("L{$row11}", 'Gula Darah');
        $sheet->mergeCells("O{$row11}:P{$row11}")->setCellValue("O{$row11}", "Skrining PUMA/PPOK\n(Usia dewasa ≥40 tahun)");
        $sheet->mergeCells("L{$row11}:N{$row11}")->setCellValue("L{$row11}", 'Gula Darah');

        $sheet->mergeCells("Q{$row11}:U{$row11}")->setCellValue("Q{$row11}", 'Tingkat Ketergantungan (AKS)');
        $sheet->mergeCells("V{$row11}:Y{$row11}")->setCellValue("V{$row11}", 'Skrining Lansia Sederhana (SKILAS)');
        $sheet->mergeCells("Z{$row11}:AC{$row11}")->setCellValue("Z{$row11}", 'Skrining Lansia Sederhana (SKILAS)');
        $sheet->mergeCells("AD{$row11}:AG{$row11}")->setCellValue("AD{$row11}", 'Skrining Lansia Sederhana (SKILAS)');
        $sheet->getStyle("B{$row11}:AG{$row11}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle("B{$row11}:AG{$row11}")->getFont()->setBold(true);

        // Baris 12: sub-header AKS & domain SKILAS
        $row12 = $row10 + 2;

        // AKS: Kategori A/B/C
        $sheet->mergeCells("Q{$row12}:R{$row12}")->setCellValue("Q{$row12}", 'Kategori A');
        $sheet->mergeCells("S{$row12}:T{$row12}")->setCellValue("S{$row12}", 'Kategori B');
        $sheet->setCellValue("U{$row12}", 'Kategori C');

        // SKILAS: domain
        $sheet->mergeCells("V{$row12}:W{$row12}")->setCellValue("V{$row12}", 'Kognitif');
        $sheet->mergeCells("X{$row12}:Y{$row12}")->setCellValue("X{$row12}", 'Gerak');
        $sheet->mergeCells("Z{$row12}:AA{$row12}")->setCellValue("Z{$row12}", 'Malnutrisi');
        $sheet->mergeCells("AB{$row12}:AC{$row12}")->setCellValue("AB{$row12}", 'Pendengaran');
        $sheet->mergeCells("AD{$row12}:AE{$row12}")->setCellValue("AD{$row12}", 'Penglihatan');
        $sheet->mergeCells("AF{$row12}:AG{$row12}")->setCellValue("AF{$row12}", 'Depresi');

        $sheet->getStyle("Q{$row12}:AG{$row12}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle("Q{$row12}:AG{$row12}")->getFont()->setBold(true);

        // Baris 12: label Tahap 1 (IMT, LP, TD, GD, PUMA)
        $headersStage1 = [
            "B{$row12}"  => 'Sangat Kurus',
            "C{$row12}"  => 'Kurus',
            "D{$row12}"  => 'Normal',
            "E{$row12}"  => 'Gemuk',
            "F{$row12}"  => 'Obesitas',
            "G{$row12}"  => "Laki-laki\n>90 Cm",
            "H{$row12}"  => "Perempuan\n>80 Cm",
            "I{$row12}"  => 'Rendah',
            "J{$row12}"  => 'Normal',
            "K{$row12}"  => 'Tinggi',
            "L{$row12}"  => 'Rendah',
            "M{$row12}"  => 'Normal',
            "N{$row12}"  => 'Tinggi',
            "O{$row12}"  => "Normal",
            "P{$row12}"  => "Tinggi",
        ];

        foreach ($headersStage1 as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }
        $sheet->getStyle("B{$row12}:P{$row12}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle("B{$row12}:P{$row12}")->getFont()->setBold(true);

        // Baris 13: detail Tahap 2 (M/R/S/B/T + Ya/Tidak + tindak lanjut)
        $row13 = $row10 + 3;

        $headersStage2 = [
            // AKS
            "O{$row13}"  => '< 6',
            "P{$row13}"  => '> 6',
            "Q{$row13}"  => 'M',
            "R{$row13}"  => 'R',
            "S{$row13}"  => 'S',
            "T{$row13}"  => 'B',
            "U{$row13}"  => 'T',

            // SKILAS
            "V{$row13}"  => 'Ya',
            "W{$row13}"  => 'Tidak',
            "X{$row13}"  => 'Ya',
            "Y{$row13}"  => 'Tidak',
            "Z{$row13}"  => 'Ya',
            "AA{$row13}" => 'Tidak',
            "AB{$row13}" => 'Ya',
            "AC{$row13}" => 'Tidak',
            "AD{$row13}" => 'Ya',
            "AE{$row13}" => 'Tidak',
            "AF{$row13}" => 'Ya',
            "AG{$row13}" => 'Tidak',
        ];

        // Baris Judul Utama (row1_1)
        $sheet->mergeCells("AH{$row9}:AH{$row13}")->setCellValue("AH{$row9}", "Jumlah Lansia\nmendapatkan\nImunisasi\nCOVID-19");
        $sheet->mergeCells("AI{$row9}:AI{$row13}")->setCellValue("AI{$row9}", "Jumlah Usia\nDewasa dan Lansia\nmendapatkan\nEdukasi");
        $sheet->mergeCells("AJ{$row9}:AJ{$row13}")->setCellValue("AJ{$row9}", "Jumlah Usia\nDewasa dan Lansia\ndirujuk");

        // Kolom B (single column spanning 3 rows)
        $sheet->mergeCells("B{$row12}:B{$row13}")->setCellValue("B{$row12}", "Sangat Kurus");
        $sheet->mergeCells("C{$row12}:C{$row13}")->setCellValue("C{$row12}", "Kurus");
        $sheet->mergeCells("D{$row12}:D{$row13}")->setCellValue("D{$row12}", "Normal");
        $sheet->mergeCells("E{$row12}:E{$row13}")->setCellValue("E{$row12}", "Gemuk");
        $sheet->mergeCells("F{$row12}:F{$row13}")->setCellValue("F{$row12}", "Obesitas");

        // Kolom G–H (Lingkar Perut)
        $sheet->mergeCells("G{$row12}:G{$row13}")->setCellValue("G{$row12}", "Laki-laki\n>90 Cm");
        $sheet->mergeCells("H{$row12}:H{$row13}")->setCellValue("H{$row12}", "Perempuan\n>80 Cm");

        // Kolom I–K (Tekanan Darah)
        $sheet->mergeCells("I{$row12}:I{$row13}")->setCellValue("I{$row12}", "Rendah");
        $sheet->mergeCells("J{$row12}:J{$row13}")->setCellValue("J{$row12}", "Normal");
        $sheet->mergeCells("K{$row12}:K{$row13}")->setCellValue("K{$row12}", "Tinggi");

        // Kolom L–N (Gula Darah)
        $sheet->mergeCells("L{$row12}:L{$row13}")->setCellValue("L{$row12}", "Rendah");
        $sheet->mergeCells("M{$row12}:M{$row13}")->setCellValue("M{$row12}", "Normal");
        $sheet->mergeCells("N{$row12}:N{$row13}")->setCellValue("N{$row12}", "Tinggi");


        foreach ($headersStage2 as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }
        $sheet->getStyle("O{$row13}:AJ{$row13}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle("O{$row13}:AJ{$row13}")->getFont()->setBold(true);

        $sheet->getStyle("AH{$row9}:AJ{$row13}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle("AH{$row9}:AJ{$row13}")->getFont()->setBold(true);

        $sheet->getStyle("AH{$row9}:AJ{$row9}")
            ->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // =====================================================================
        // BARIS 14 → NOMOR KOLOM AKS + SKILAS + BACKGROUND WARNA ABU2 (BFBFBF)
        // =====================================================================
        $row14 = $row10 + 4;

        $iterator = $sheet->getColumnIterator('A', 'AJ');

        $noAks = 1;
        foreach ($iterator as $column) {
            $col = $column->getColumnIndex(); // A … AJ
            $sheet->setCellValue("{$col}{$row14}", $noAks++);
        }

        $sheet->getStyle("A{$row14}:AJ{$row14}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->getRowDimension(20)->setRowHeight(20);
        $sheet->getStyle("A{$row14}:AJ{$row14}")->getFont()->setBold(true);

        $sheet->getStyle("A{$row14}:AJ{$row14}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB("FFBFBFBF");

        // BACKGROUND COLOR HEADER
        $sheet->getStyle("A{$row9}:AJ{$row13}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB("D8D8D8");

        // ================== ISI 12 BULAN ==================
        $firstDataRow = $row10 + 5; // = 15
        $row          = $firstDataRow;
        $adaData      = false;

        for ($bulan = 1; $bulan <= 12; $bulan++) {
            $start = Carbon::create($tahun, $bulan, 1)->startOfMonth();
            $end   = (clone $start)->endOfMonth();

            // --------- Dewasa ---------
            $aggD = PemeriksaanDewasaLansia::query()
                ->leftJoin('warga', 'warga.id', '=', 'pemeriksaan_dewasa_lansia.warga_id')
                ->whereBetween('tanggal_periksa', [$start->toDateString(), $end->toDateString()])
                ->selectRaw('COUNT(*) as total_pemeriksaan')
                ->selectRaw("SUM(CASE WHEN kategori_imt = 'SK' THEN 1 ELSE 0 END) as imt_sangat_kurus")
                ->selectRaw("SUM(CASE WHEN kategori_imt = 'K'  THEN 1 ELSE 0 END) as imt_kurus")
                ->selectRaw("SUM(CASE WHEN kategori_imt = 'N'  THEN 1 ELSE 0 END) as imt_normal")
                ->selectRaw("SUM(CASE WHEN kategori_imt = 'G'  THEN 1 ELSE 0 END) as imt_gemuk")
                ->selectRaw("SUM(CASE WHEN kategori_imt = 'O'  THEN 1 ELSE 0 END) as imt_obesitas")
                ->selectRaw("
                    SUM(
                        CASE 
                            WHEN (warga.jenis_kelamin IN ('L','Laki-laki','Laki')) 
                                 AND lingkar_perut > 90 
                            THEN 1 ELSE 0 
                        END
                    ) as lp_laki
                ")
                ->selectRaw("
                    SUM(
                        CASE 
                            WHEN (warga.jenis_kelamin IN ('P','Perempuan')) 
                                 AND lingkar_perut > 80 
                            THEN 1 ELSE 0 
                        END
                    ) as lp_perempuan
                ")
                ->selectRaw("SUM(CASE WHEN td_kategori = 'N' THEN 1 ELSE 0 END) as td_normal")
                ->selectRaw("SUM(CASE WHEN td_kategori = 'T' THEN 1 ELSE 0 END) as td_tinggi")
                ->selectRaw("SUM(CASE WHEN gd_kategori = 'N' THEN 1 ELSE 0 END) as gd_normal")
                ->selectRaw("SUM(CASE WHEN gd_kategori = 'T' THEN 1 ELSE 0 END) as gd_tinggi")
                ->selectRaw("SUM(CASE WHEN skor_puma > 0 AND skor_puma <= 6 THEN 1 ELSE 0 END) as puma_normal")
                ->selectRaw("SUM(CASE WHEN skor_puma > 6 THEN 1 ELSE 0 END) as puma_tinggi")
                ->selectRaw("SUM(CASE WHEN edukasi IS NOT NULL AND edukasi <> '' THEN 1 ELSE 0 END) as edukasi_dewasa")
                ->selectRaw("SUM( (tbc_rujuk = 1) OR (rujuk_puskesmas = 1) ) as dirujuk_dewasa")
                ->first();

            // --------- Lansia ---------
            $aggL = PemeriksaanLansia::query()
                ->whereBetween('tanggal_periksa', [$start->toDateString(), $end->toDateString()])
                ->selectRaw('COUNT(*) as total_lansia')
                ->selectRaw("SUM(CASE WHEN aks_kategori = 'M' THEN 1 ELSE 0 END) as aks_M")
                ->selectRaw("SUM(CASE WHEN aks_kategori = 'R' THEN 1 ELSE 0 END) as aks_R")
                ->selectRaw("SUM(CASE WHEN aks_kategori = 'S' THEN 1 ELSE 0 END) as aks_S")
                ->selectRaw("SUM(CASE WHEN aks_kategori = 'B' THEN 1 ELSE 0 END) as aks_B")
                ->selectRaw("SUM(CASE WHEN aks_kategori = 'T' THEN 1 ELSE 0 END) as aks_T")
                ->selectRaw("SUM(CASE WHEN skil_orientasi_waktu_tempat = 0 THEN 1 ELSE 0 END) as skil_kognitif_ya")
                ->selectRaw("SUM(CASE WHEN skil_orientasi_waktu_tempat <> 0 THEN 1 ELSE 0 END) as skil_kognitif_tidak")
                ->selectRaw("SUM(CASE WHEN skil_tes_berdiri_dari_kursi = 0 THEN 1 ELSE 0 END) as skil_gerak_ya")
                ->selectRaw("SUM(CASE WHEN skil_tes_berdiri_dari_kursi <> 0 THEN 1 ELSE 0 END) as skil_gerak_tidak")
                ->selectRaw("SUM(CASE WHEN skil_bb_berkurang_3kg_dalam_3bulan = 1 THEN 1 ELSE 0 END) as skil_malnutrisi_ya")
                ->selectRaw("SUM(CASE WHEN skil_bb_berkurang_3kg_dalam_3bulan <> 1 THEN 1 ELSE 0 END) as skil_malnutrisi_tidak")
                ->selectRaw("SUM(CASE WHEN skil_tes_bisik = 0 THEN 1 ELSE 0 END) as skil_pendengaran_ya")
                ->selectRaw("SUM(CASE WHEN skil_tes_bisik <> 0 THEN 1 ELSE 0 END) as skil_pendengaran_tidak")
                ->selectRaw("SUM(CASE WHEN (skil_masalah_pada_mata = 1 OR skil_tes_melihat = 0) THEN 1 ELSE 0 END) as skil_penglihatan_ya")
                ->selectRaw("SUM(CASE WHEN (skil_masalah_pada_mata = 1 OR skil_tes_melihat = 0) = 0 THEN 1 ELSE 0 END) as skil_penglihatan_tidak")
                ->selectRaw("SUM(CASE WHEN (skil_perasaan_sedih_tertekan = 1 OR skil_sedikit_minat_atau_kenikmatan = 1) THEN 1 ELSE 0 END) as skil_depresi_ya")
                ->selectRaw("SUM(CASE WHEN (skil_perasaan_sedih_tertekan = 1 OR skil_sedikit_minat_atau_kenikmatan = 1) = 0 THEN 1 ELSE 0 END) as skil_depresi_tidak")
                ->selectRaw("SUM(COALESCE(skil_imunisasi_covid,0)) as imunisasi_covid")
                ->selectRaw("SUM(CASE WHEN skil_edukasi IS NOT NULL AND skil_edukasi <> '' THEN 1 ELSE 0 END) as edukasi_lansia")
                ->selectRaw("
                    SUM(
                        (COALESCE(aks_rujuk_otomatis,0) = 1) OR
                        (COALESCE(aks_rujuk_manual,0)   = 1) OR
                        (COALESCE(skil_rujuk_otomatis,0)= 1) OR
                        (COALESCE(skil_rujuk_manual,0)  = 1)
                    ) as dirujuk_lansia
                ")
                ->first();

            if ($aggD && $aggD->total_pemeriksaan > 0) $adaData = true;
            if ($aggL && $aggL->total_lansia > 0)      $adaData = true;

            $bulanNama = $start->translatedFormat('F Y');

            // Tahap 1: A–P
            $sheet->setCellValue("A{$row}", $bulanNama);
            $sheet->setCellValue("B{$row}", (int)($aggD->imt_sangat_kurus ?? 0));
            $sheet->setCellValue("C{$row}", (int)($aggD->imt_kurus ?? 0));
            $sheet->setCellValue("D{$row}", (int)($aggD->imt_normal ?? 0));
            $sheet->setCellValue("E{$row}", (int)($aggD->imt_gemuk ?? 0));
            $sheet->setCellValue("F{$row}", (int)($aggD->imt_obesitas ?? 0));
            $sheet->setCellValue("G{$row}", (int)($aggD->lp_laki ?? 0));
            $sheet->setCellValue("H{$row}", (int)($aggD->lp_perempuan ?? 0));
            $sheet->setCellValue("I{$row}", 0);
            $sheet->setCellValue("J{$row}", (int)($aggD->td_normal ?? 0));
            $sheet->setCellValue("K{$row}", (int)($aggD->td_tinggi ?? 0));
            $sheet->setCellValue("L{$row}", 0);
            $sheet->setCellValue("M{$row}", (int)($aggD->gd_normal ?? 0));
            $sheet->setCellValue("N{$row}", (int)($aggD->gd_tinggi ?? 0));
            $sheet->setCellValue("O{$row}", (int)($aggD->puma_normal ?? 0));
            $sheet->setCellValue("P{$row}", (int)($aggD->puma_tinggi ?? 0));

            // Tahap 2: Q–AJ
            $sheet->setCellValue("Q{$row}", (int)($aggL->aks_M ?? 0));
            $sheet->setCellValue("R{$row}", (int)($aggL->aks_R ?? 0));
            $sheet->setCellValue("S{$row}", (int)($aggL->aks_S ?? 0));
            $sheet->setCellValue("T{$row}", (int)($aggL->aks_B ?? 0));
            $sheet->setCellValue("U{$row}", (int)($aggL->aks_T ?? 0));

            $sheet->setCellValue("V{$row}", (int)($aggL->skil_kognitif_ya ?? 0));
            $sheet->setCellValue("W{$row}", (int)($aggL->skil_kognitif_tidak ?? 0));
            $sheet->setCellValue("X{$row}", (int)($aggL->skil_gerak_ya ?? 0));
            $sheet->setCellValue("Y{$row}", (int)($aggL->skil_gerak_tidak ?? 0));
            $sheet->setCellValue("Z{$row}", (int)($aggL->skil_malnutrisi_ya ?? 0));
            $sheet->setCellValue("AA{$row}", (int)($aggL->skil_malnutrisi_tidak ?? 0));
            $sheet->setCellValue("AB{$row}", (int)($aggL->skil_pendengaran_ya ?? 0));
            $sheet->setCellValue("AC{$row}", (int)($aggL->skil_pendengaran_tidak ?? 0));
            $sheet->setCellValue("AD{$row}", (int)($aggL->skil_penglihatan_ya ?? 0));
            $sheet->setCellValue("AE{$row}", (int)($aggL->skil_penglihatan_tidak ?? 0));
            $sheet->setCellValue("AF{$row}", (int)($aggL->skil_depresi_ya ?? 0));
            $sheet->setCellValue("AG{$row}", (int)($aggL->skil_depresi_tidak ?? 0));

            $sheet->setCellValue("AH{$row}", (int)($aggL->imunisasi_covid ?? 0));

            $edukasiTotal = (int)($aggD->edukasi_dewasa ?? 0) + (int)($aggL->edukasi_lansia ?? 0);
            $sheet->setCellValue("AI{$row}", $edukasiTotal);

            $dirujukTotal = (int)($aggD->dirujuk_dewasa ?? 0) + (int)($aggL->dirujuk_lansia ?? 0);
            $sheet->setCellValue("AJ{$row}", $dirujukTotal);

            $row++;
        }

        if (!$adaData) {
            return back()->with('warning', "Tidak ada data pemeriksaan pada tahun {$tahun}.");
        }

        // ================== STYLING TABEL ==================
        $lastRow = $row - 1;

        $styleAll = $sheet->getStyle("A{$row10}:AJ{$lastRow}");

        $styleAll->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        $styleAll->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        $sheet->getColumnDimension('A')->setWidth(18);
        foreach (range('B','Z') as $col) {
            $sheet->getColumnDimension($col)->setWidth(12);
        }
        foreach (['AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ'] as $col) {
            $sheet->getColumnDimension($col)->setWidth(14);
        }

        $sheet->getRowDimension($row11)->setRowHeight(30);
        $sheet->getRowDimension($row12)->setRowHeight(30);
        $sheet->getRowDimension($row13)->setRowHeight(55);

        // ================== DOWNLOAD ==================
        $filename = "Rekap_Kemenkes_Tahun_{$tahun}.xlsx";
        $writer = new Xlsx($spreadsheet);

        if (ob_get_length()) {
            @ob_end_clean();
        }

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }


}
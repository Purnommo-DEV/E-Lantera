<?php

namespace App\Http\Controllers;

use App\Models\PemeriksaanLansia;
use App\Models\Warga;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Style\Color;

class PemeriksaanLansiaController extends Controller
{
    // public function exportLansiaExcel(Warga $warga)
    // {
    //     // Ambil semua riwayat pemeriksaan lansia (pastikan relasi ini ada di model Warga)
    //     // public function pemeriksaanLansiaAll() { return $this->hasMany(PemeriksaanLansia::class, 'warga_id')->orderBy('tanggal_periksa', 'desc'); }
    //     $periksas = $warga->pemeriksaanLansiaAll;

    //     if ($periksas->isEmpty()) {
    //         abort(404, 'Belum ada data pemeriksaan lansia untuk warga ini');
    //     }

    //     $spreadsheet = new Spreadsheet();
    //     $sheet       = $spreadsheet->getActiveSheet();

    //     //|--------------------------------------------------------------------------
    //     // HEADER & IDENTITAS (POSISI TIDAK DIUBAH)
    //     // ==================== JUDUL ====================
    //     $sheet->setCellValue('A2', 'KARTU BANTU PEMERIKSAAN LANSIA (≥60 Tahun)');
    //     $sheet->mergeCells('A2:AC2');
    //     $sheet->getStyle('A2:AC2')->getFont()->setSize(16)->setBold(true);
    //     $sheet->getStyle('A2:AC2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    //     $sheet->setCellValue('A3', 'POSYANDU TAMAN CIPULIR ESTATE');
    //     $sheet->mergeCells('A3:AC3');
    //     $sheet->getStyle('A3:AC3')->getFont()->setSize(16)->setBold(true);
    //     $sheet->getStyle('A3:AC3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    //     // ==================== IDENTITAS ====================
    //     $labelsA = [
    //         'A5'  => 'Nama',
    //         'A6'  => 'NIK',
    //         'A7'  => 'Tanggal Lahir',
    //         'A8'  => 'Alamat',
    //         'A9'  => 'No. HP',
    //         'A10' => 'Status Perkawinan',
    //         'A11' => 'Pekerjaan',
    //         'A12' => 'Dusun/RT/RW',
    //         'A13' => 'Kecamatan',
    //         'A14' => 'Desa/Kelurahan/Nagari'
    //     ];

    //     foreach ($labelsA as $cell => $text) {
    //         $sheet->setCellValue($cell, $text);
    //         $style = $sheet->getStyle($cell);
    //         $style->getFont()->setSize(16)->setBold(true);
    //         $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    //     }

    //     $labelsB = [
    //         'B5'  => ':',
    //         'B6'  => ':',
    //         'B7'  => ':',
    //         'B8'  => ':',
    //         'B9'  => ':',
    //         'B10' => ':',
    //         'B11' => ':',
    //         'B12' => ':',
    //         'B13' => ':',
    //         'B14' => ':'
    //     ];

    //     foreach ($labelsB as $cell => $text) {
    //         $sheet->setCellValue($cell, $text);
    //         $style = $sheet->getStyle($cell);
    //         $style->getFont()->setSize(12)->setBold(false);
    //         $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    //     }

    //     // ISI IDENTITAS (kolom C)
    //     $dataIdentitas = [
    //         5  => $warga->nama,
    //         6  => $warga->nik,
    //         7  => $warga->tanggal_lahir ? Carbon::parse($warga->tanggal_lahir)->format('d-m-Y') : '-',
    //         8  => $warga->alamat,
    //         9  => $warga->no_hp,
    //         10 => $warga->status_nikah,
    //         11 => $warga->pekerjaan,
    //         12 => sprintf('%s/%s/%s', $warga->dusun ?? '-', $warga->rt ?? '-', $warga->rw ?? '-'),
    //         13 => $warga->kecamatan,
    //         14 => $warga->desa,
    //     ];

    //     foreach ($dataIdentitas as $row => $value) {
    //         $sheet->setCellValue("C{$row}", $value ?? '-');
    //     }

    //     // Tambahan: ( Laki-laki / Perempuan ) di D5
    //     $jenisRaw = trim($warga->jenis_kelamin ?? '');
    //     $richText = new RichText();
    //     $richText->createTextRun('( ');

    //     $textL = $richText->createTextRun('Laki-laki');
    //     if (strcasecmp($jenisRaw, 'Laki-laki') === 0 || $jenisRaw === 'L') {
    //         $textL->getFont()->getColor()->setARGB(Color::COLOR_RED);
    //     }

    //     $richText->createTextRun(' / ');

    //     $textP = $richText->createTextRun('Perempuan');
    //     if (strcasecmp($jenisRaw, 'Perempuan') === 0 || $jenisRaw === 'P') {
    //         $textP->getFont()->getColor()->setARGB(Color::COLOR_RED);
    //     }

    //     $richText->createTextRun(' )');
    //     $sheet->setCellValue('D5', $richText);

    //     // Umur (contoh: ( 80 Tahun )) di D6
    //     if ($warga->tanggal_lahir) {
    //         $lahir = Carbon::parse($warga->tanggal_lahir);
    //         $tahun = $lahir->diff(now())->y;
    //     } else {
    //         $tahun = 0;
    //     }
    //     $sheet->setCellValue('D6', '( ' . $tahun . ' Tahun )');

    //     // ==================== RIWAYAT KELUARGA / DIRI SENDIRI / PERILAKU (P5–AA12) ====================
    //     // Judul: Riwayat Keluarga (lingkari jika ada)
    //     $sheet->mergeCells('P5:Q6');
    //     $sheet->setCellValue('P5', "Riwayat Keluarga\n(lingkari jika ada)");
    //     $sheet->getStyle('P5:Q6')->getAlignment()
    //         ->setHorizontal(Alignment::HORIZONTAL_LEFT)
    //         ->setVertical(Alignment::VERTICAL_TOP)
    //         ->setWrapText(true);

    //     // Pilihan riwayat keluarga
    //     $sheet->setCellValue('R5', 'a. Hipertensi');
    //     $sheet->setCellValue('S5', 'b. DM');
    //     $sheet->setCellValue('T5', 'c. Stroke');
    //     $sheet->setCellValue('U5', 'd. Jantung');
    //     $sheet->setCellValue('V5', 'f. Kanker');
    //     $sheet->setCellValue('W5', 'g. Kolesterol Tinggi');

    //     // Judul: Riwayat Diri Sendiri (lingkari jika ada)
    //     $sheet->mergeCells('P7:Q8');
    //     $sheet->setCellValue('P7', "Riwayat Diri Sendiri\n(lingkari jika ada)");
    //     $sheet->getStyle('P7:Q8')->getAlignment()
    //         ->setHorizontal(Alignment::HORIZONTAL_LEFT)
    //         ->setVertical(Alignment::VERTICAL_TOP)
    //         ->setWrapText(true);

    //     // Pilihan riwayat diri sendiri
    //     $sheet->setCellValue('R7', 'a. Hipertensi');
    //     $sheet->setCellValue('S7', 'b. DM');
    //     $sheet->setCellValue('T7', 'c. Stroke');
    //     $sheet->setCellValue('U7', 'd. Jantung');
    //     $sheet->setCellValue('V7', 'f. Kanker');
    //     $sheet->setCellValue('W7', 'g. Kolesterol Tinggi');

    //     // Judul: Perilaku Berisiko Diri Sendiri (lingkari jika ada)
    //     $sheet->mergeCells('P9:Q12');
    //     $sheet->setCellValue('P9', "Perilaku Berisiko Diri Sendiri\n(lingkari jika ada)");
    //     $sheet->getStyle('P9:Q12')->getAlignment()
    //         ->setHorizontal(Alignment::HORIZONTAL_LEFT)
    //         ->setVertical(Alignment::VERTICAL_TOP)
    //         ->setWrapText(true);

    //     // Item perilaku berisiko
    //     $sheet->setCellValue('R9',  'a. Merokok');
    //     $sheet->setCellValue('R10', 'b. Konsumsi Tinggi Gula');
    //     $sheet->setCellValue('R11', 'c. Konsumsi Tinggi Garam');
    //     $sheet->setCellValue('R12', 'd. Konsumsi Tinggi Lemak');

    //     // Kolom keterangan Ya/Tidak
    //     $sheet->setCellValue('X9',  ': Ya/Tidak');
    //     $sheet->setCellValue('X10', ': Ya/Tidak');
    //     $sheet->setCellValue('X11', ': Ya/Tidak');
    //     $sheet->setCellValue('X12', ': Ya/Tidak');

    //     // Langkah-langkah di AC8–AC12
    //     $steps = [
    //         'AC8'  => ['text' => ': Disi langkah 1', 'color' => 'FFFCE2D2'],
    //         'AC9'  => ['text' => ': Disi langkah 2', 'color' => 'FFFFE79B'],
    //         'AC10' => ['text' => ': Disi langkah 3', 'color' => 'FFFFFFCC'],
    //         'AC11' => ['text' => ': Disi langkah 4', 'color' => 'FFD7E1F3'],
    //         'AC12' => ['text' => ': Disi langkah 5', 'color' => 'FFCCCCFF'],
    //     ];

    //     foreach ($steps as $cell => $v) {
    //         $sheet->setCellValue($cell, $v['text']);
    //         $sheet->getStyle($cell)->getFill()
    //             ->setFillType(Fill::FILL_SOLID)
    //             ->getStartColor()->setARGB($v['color']);
    //     }

    //     $sheet->getStyle('P5:AA12')->getFont()->setSize(11);
    //     $sheet->getStyle('P5:AA12')->getAlignment()
    //         ->setVertical(Alignment::VERTICAL_TOP)
    //         ->setWrapText(true);
    //     //|--------------------------------------------------------------------------

    //     /**
    //      * ======================================================================
    //      *  MULAI BAGIAN LANSIA: TABEL AKS + SKILAS DALAM 1 BARIS (1 SHEET)
    //      * ======================================================================
    //      */

    //     // Judul bagian AKS & SKILAS
    //     $sheet->mergeCells('A16:AC16');
    //     $sheet->setCellValue('A16', 'Pemeriksaan Lansia - AKS & SKILAS');
    //     $sheet->getStyle('A16')->getFont()->setBold(true)->setSize(14);
    //     $sheet->getStyle('A16')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    //     // Header baris gabungan AKS (kiri) dan SKILAS (kanan)
    //     // Baris 18 = judul area kiri/kanan
    //     $sheet->mergeCells('A18:N18');
    //     $sheet->setCellValue('A18', 'AKS - Aktivitas Kehidupan Sehari-hari (Barthel Index)');
    //     $sheet->getStyle('A18')->getFont()->setBold(true);
    //     $sheet->getStyle('A18')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    //     $sheet->mergeCells('O18:AC18');
    //     $sheet->setCellValue('O18', 'SKILAS - Skrining Risiko Lansia (Jika ≥1 YA → Wajib Rujuk)');
    //     $sheet->getStyle('O18')->getFont()->setBold(true);
    //     $sheet->getStyle('O18')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    //     // Baris 19 = header kolom detail (SATU BARIS untuk AKS + SKILAS)
    //     // --- AKS (kiri) ---
    //     $aksHeader = [
    //         'A19' => 'Tanggal',
    //         'B19' => 'BAB',
    //         'C19' => 'BAK',
    //         'D19' => 'Membersihkan Diri',
    //         'E19' => 'Ke WC',
    //         'F19' => 'Makan',
    //         'G19' => 'Berpindah',
    //         'H19' => 'Berjalan',
    //         'I19' => 'Berpakaian',
    //         'J19' => 'Naik Tangga',
    //         'K19' => 'Mandi',
    //         'L19' => 'Total Skor',
    //         'M19' => 'Kategori',
    //         'N19' => 'Rujuk AKS?',
    //     ];

    //     foreach ($aksHeader as $cell => $text) {
    //         $sheet->setCellValue($cell, $text);
    //         $sheet->getStyle($cell)->getFont()->setBold(true);
    //         $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    //         $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)
    //             ->getStartColor()->setARGB('FFE1F0FF');
    //     }

    //     // --- SKILAS (kanan) ---
    //     $skilasHeader = [
    //         'O19'  => 'Orientasi',
    //         'P19'  => '3 Kata',
    //         'Q19'  => 'Berdiri Kursi',
    //         'R19'  => 'BB Turun',
    //         'S19'  => 'Nafsu Makan',
    //         'T19'  => 'LLA <21',
    //         'U19'  => 'Keluhan Mata',
    //         'V19'  => 'Tes Melihat',
    //         'W19'  => 'Tes Bisik',
    //         'X19'  => 'Sedih/Tertekan',
    //         'Y19'  => 'Aktivitas Hilang',
    //         'Z19'  => 'Minat Turun',
    //         'AA19' => 'Vaksin COVID',
    //         'AB19' => 'Rujuk SKILAS?',
    //         'AC19' => 'Catatan',
    //     ];

    //     foreach ($skilasHeader as $cell => $text) {
    //         $sheet->setCellValue($cell, $text);
    //         $sheet->getStyle($cell)->getFont()->setBold(true);
    //         $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    //         $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)
    //             ->getStartColor()->setARGB('FFFFE0E0');
    //     }

    //     // Garis batas tebal di tengah (antara N dan O)
    //     $sheet->getStyle('N19:N1000')->getBorders()->getRight()->setBorderStyle(Border::BORDER_MEDIUM);

    //     // ==================== ISI DATA PER RIWAYAT ====================
    //     $row = 20;

    //     foreach ($periksas as $periksa) {

    //         // ---- Tanggal ----
    //         $sheet->setCellValue("A{$row}", $periksa->tanggal_periksa
    //             ? Carbon::parse($periksa->tanggal_periksa)->format('d-m-Y')
    //             : '-'
    //         );

    //         // ----------- AKS -----------

    //         // BAB
    //         $bab = '-';
    //         if ($periksa->aks_bab_s0_tidak_terkendali ?? false) {
    //             $bab = 'Tidak terkendali / tak teratur';
    //         } elseif ($periksa->aks_bab_s1_kadang_tak_terkendali ?? false) {
    //             $bab = 'Kadang-kadang tak terkendali';
    //         }
    //         $sheet->setCellValue("B{$row}", $bab);

    //         // BAK
    //         $bak = '-';
    //         if ($periksa->aks_bak_s0_tidak_terkendali_kateter ?? false) {
    //             $bak = 'Tidak terkendali / kateter';
    //         } elseif ($periksa->aks_bak_s1_kadang_1x24jam ?? false) {
    //             $bak = 'Kadang-kadang tak terkendali';
    //         } elseif ($periksa->aks_bak_s2_mandiri ?? false) {
    //             $bak = 'Mandiri';
    //         }
    //         $sheet->setCellValue("C{$row}", $bak);

    //         // Membersihkan Diri
    //         $diri = '-';
    //         if ($periksa->aks_diri_s0_butuh_orang_lain ?? false) {
    //             $diri = 'Butuh bantuan';
    //         } elseif ($periksa->aks_diri_s1_mandiri ?? false) {
    //             $diri = 'Mandiri';
    //         }
    //         $sheet->setCellValue("D{$row}", $diri);

    //         // Ke WC
    //         $wc = '-';
    //         if ($periksa->aks_wc_s0_tergantung_lain ?? false) {
    //             $wc = 'Tergantung orang lain';
    //         } elseif ($periksa->aks_wc_s1_perlu_beberapa_bisa_sendiri ?? false) {
    //             $wc = 'Perlu bantuan';
    //         } elseif ($periksa->aks_wc_s2_mandiri ?? false) {
    //             $wc = 'Mandiri';
    //         }
    //         $sheet->setCellValue("E{$row}", $wc);

    //         // Makan
    //         $makan = '-';
    //         if ($periksa->aks_makan_s0_tidak_mampu ?? false) {
    //             $makan = 'Tidak mampu';
    //         } elseif ($periksa->aks_makan_s1_perlu_pemotongan ?? false) {
    //             $makan = 'Perlu bantuan';
    //         } elseif ($periksa->aks_makan_s2_mandiri ?? false) {
    //             $makan = 'Mandiri';
    //         }
    //         $sheet->setCellValue("F{$row}", $makan);

    //         // Berpindah
    //         $bergerak = '-';
    //         if ($periksa->aks_bergerak_s0_tidak_mampu ?? false) {
    //             $bergerak = 'Tidak mampu';
    //         } elseif ($periksa->aks_bergerak_s1_butuh_2orang ?? false) {
    //             $bergerak = 'Butuh 2 orang';
    //         } elseif ($periksa->aks_bergerak_s2_butuh_1orang ?? false) {
    //             $bergerak = 'Butuh 1 orang';
    //         } elseif ($periksa->aks_bergerak_s3_mandiri ?? false) {
    //             $bergerak = 'Mandiri';
    //         }
    //         $sheet->setCellValue("G{$row}", $bergerak);

    //         // Berjalan
    //         $jalan = '-';
    //         if ($periksa->aks_jalan_s0_tidak_mampu ?? false) {
    //             $jalan = 'Tidak mampu';
    //         } elseif ($periksa->aks_jalan_s1_kursi_roda ?? false) {
    //             $jalan = 'Kursi roda';
    //         } elseif ($periksa->aks_jalan_s2_bantuan_1orang ?? false) {
    //             $jalan = 'Bantuan 1 orang';
    //         } elseif ($periksa->aks_jalan_s3_mandiri ?? false) {
    //             $jalan = 'Mandiri';
    //         }
    //         $sheet->setCellValue("H{$row}", $jalan);

    //         // Berpakaian
    //         $pakaian = '-';
    //         if ($periksa->aks_pakaian_s0_tergantung_lain ?? false) {
    //             $pakaian = 'Tergantung orang lain';
    //         } elseif ($periksa->aks_pakaian_s1_sebagian_dibantu ?? false) {
    //             $pakaian = 'Sebagian dibantu';
    //         } elseif ($periksa->aks_pakaian_s2_mandiri ?? false) {
    //             $pakaian = 'Mandiri';
    //         }
    //         $sheet->setCellValue("I{$row}", $pakaian);

    //         // Naik tangga
    //         $tangga = '-';
    //         if ($periksa->aks_tangga_s0_tidak_mampu ?? false) {
    //             $tangga = 'Tidak mampu';
    //         } elseif ($periksa->aks_tangga_s1_butuh_bantuan ?? false) {
    //             $tangga = 'Butuh bantuan';
    //         } elseif ($periksa->aks_tangga_s2_mandiri ?? false) {
    //             $tangga = 'Mandiri';
    //         }
    //         $sheet->setCellValue("J{$row}", $tangga);

    //         // Mandi
    //         $mandi = '-';
    //         if ($periksa->aks_mandi_s0_tergantung_lain ?? false) {
    //             $mandi = 'Tergantung orang lain';
    //         } elseif ($periksa->aks_mandi_s1_mandiri ?? false) {
    //             $mandi = 'Mandiri';
    //         }
    //         $sheet->setCellValue("K{$row}", $mandi);

    //         // Total skor, kategori, rujuk?
    //         $sheet->setCellValue("L{$row}", $periksa->aks_total_skor ?? 0);
    //         $sheet->setCellValue("M{$row}", $periksa->aks_kategori ?? '-');
    //         $sheet->setCellValue("N{$row}", ($periksa->aks_perlu_rujuk ?? false) ? 'YA' : 'Tidak');

    //         // ----------- SKILAS -----------
    //         $skilFields = [
    //             'O' => $periksa->skil_orientasi_waktu_tempat ?? 0,
    //             'P' => $periksa->skil_mengulang_ketiga_kata ?? 0,
    //             'Q' => $periksa->skil_tes_berdiri_dari_kursi ?? 0,
    //             'R' => $periksa->skil_bb_berkurang_3kg_dalam_3bulan ?? 0,
    //             'S' => $periksa->skil_hilang_nafsu_makan ?? 0,
    //             'T' => $periksa->skil_lla_kurang_21cm ?? 0,
    //             'U' => $periksa->skil_masalah_pada_mata ?? 0,
    //             'V' => $periksa->skil_tes_melihat ?? 0,
    //             'W' => $periksa->skil_tes_bisik ?? 0,
    //             'X' => $periksa->skil_perasaan_sedih_tertekan ?? 0,
    //             'Y' => $periksa->skil_tidak_dapat_dilakukan ?? 0,
    //             'Z' => $periksa->skil_sedikit_minat_atau_kenikmatan ?? 0,
    //             'AA'=> $periksa->skil_imunisasi_covid ?? 0,
    //         ];

    //         $totalYaSkilas = 0;
    //         foreach ($skilFields as $col => $val) {
    //             $isYa = (int)$val === 1;
    //             if ($isYa) $totalYaSkilas++;
    //             $sheet->setCellValue("{$col}{$row}", $isYa ? 'Ya' : 'Tidak');
    //         }

    //         // Rujuk SKILAS? (kalau ada 1 atau lebih YA, atau ada flag rujuk otomatis/manual)
    //         $perluRujukSkilas = $totalYaSkilas > 0
    //             || ($periksa->skil_rujuk_otomatis ?? false)
    //             || ($periksa->skil_rujuk_manual ?? false);

    //         $sheet->setCellValue("AB{$row}", $perluRujukSkilas ? 'YA' : 'Tidak');
    //         $sheet->setCellValue("AC{$row}", $periksa->skil_catatan ?? '-');

    //         $row++;
    //     }

    //     $lastRow = $row - 1;

    //     // BORDER & ALIGNMENT untuk area tabel AKS+SKILAS
    //     $sheet->getStyle("A19:AC{$lastRow}")
    //         ->getBorders()->getAllBorders()
    //         ->setBorderStyle(Border::BORDER_THIN);

    //     $sheet->getStyle("A19:AC{$lastRow}")
    //         ->getAlignment()
    //         ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    //         ->setVertical(Alignment::VERTICAL_CENTER)
    //         ->setWrapText(true);

    //     // Auto width semua kolom
    //     foreach (array_merge(range('A', 'Z'), ['AA', 'AB', 'AC']) as $col) {
    //         $sheet->getColumnDimension($col)->setAutoSize(true);
    //     }

    //     // DOWNLOAD
    //     $filename = "Kartu_Pemeriksaan_Lansia_{$warga->nik}.xlsx";
    //     header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    //     header('Content-Disposition: attachment; filename="' . $filename . '"');
    //     header('Cache-Control: max-age=0');

    //     $writer = new Xlsx($spreadsheet);
    //     $writer->save('php://output');
    //     exit;
    // }


public function exportLansiaExcel(Warga $warga)
{
    $periksas = $warga->pemeriksaanLansiaAll;
    if ($periksas->isEmpty()) {
        abort(404, 'Belum ada data pemeriksaan lansia');
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // ======================
    // KONFIGURASI GLOBAL FONT
    // ======================
    $fontSizeDefaultData   = 10; // ukuran teks umum (isi tabel, dll)
    $fontSizeHeaderUtama   = 16; // judul paling atas (A2, A3)
    $fontSizeHeaderBlok    = 14; // judul blok seperti "AKS", "SKILAS"
    $fontSizeHeaderKecil   = 11; // subjudul / teks penjelasan di header
    $fontSizeProfil        = 11; // subjudul / teks penjelasan di header
    $fontSizeDepanBelakang = 9; // subjudul / teks penjelasan di header

    $headerTopRow    = 18;
    $optionRowStart  = 20;
    $optionRowEnd    = 21;
    $aksFirstDataRow = 23;


    // set default font utk SELURUH SHEET
    $spreadsheet->getDefaultStyle()->getFont()
        ->setName('Calibri')        // bebas, bisa ganti
        ->setSize($fontSizeDefaultData);

    // Helper untuk range kolom 2 huruf (AJ, AK, ..., AY)
    $excelColumnRange = function (string $start, string $end): array {
        $cols = [];
        $current = $start;
        while (true) {
            $cols[] = $current;
            if ($current === $end) {
                break;
            }
            $current++;
        }
        return $cols;
    };

    // =====================================================================
    // 1. HEADER UTAMA & IDENTITAS AKS
    // =====================================================================
    $sheet->setCellValue('A1', 'Depan');
    $sheet->mergeCells('A1:AH1');

    // FONT
    $sheet->getStyle('A1:AH1')->getFont()
        ->setSize($fontSizeDepanBelakang)
        ->setBold(true)
        ->getColor()->setARGB('FFFFFFFF'); // putih

    // ALIGNMENT
    $sheet->getStyle('A1:AH1')->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);

    // BACKGROUND
    $sheet->getStyle('A1:AH1')->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FF00B050'); // hijau 00B050

    $sheet->setCellValue('A2', 'KARTU BANTU PEMERIKSAAN LANSIA (≥60 Tahun)');
    $sheet->mergeCells('A2:AH2');
    $sheet->getStyle('A2:AH2')->getFont()->setSize($fontSizeHeaderUtama)->setBold(true);
    $sheet->getStyle('A2:AH2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->setCellValue('A3', 'POSYANDU TAMAN CIPULIR ESTATE');
    $sheet->mergeCells('A3:AH3');
    $sheet->getStyle('A3:AH3')->getFont()->setSize($fontSizeHeaderUtama)->setBold(true);
    $sheet->getStyle('A3:AH3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Label identitas AKS
    $labelsAKS = [
        'A5' => 'Nama',
        'A6' => 'NIK',
        'A7' => 'Tanggal Lahir',
        'A8' => 'Alamat',
        'A9' => 'No. HP',
        'A10' => 'Status Perkawinan',
        'A11' => 'Pekerjaan',
        'A12' => 'Dusun/RT/RW',
        'A13' => 'Kecamatan',
        'A14' => 'Desa/Kelurahan/Nagari',
    ];

    foreach ($labelsAKS as $cell => $text) {
        // ambil baris dari alamat sel, misal "A5" → 5
        $row = preg_replace('/\D/', '', $cell);

        // merge A dan B per baris
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

    // Tanda titik dua di kolom C
    foreach (['C5','C6','C7','C8','C9','C10','C11','C12','C13','C14'] as $cell) {
        $sheet->setCellValue($cell, ':');
        $sheet->getStyle($cell)->getFont()->setSize(12);
        $sheet->getStyle($cell)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER);
    }

    // Isi identitas di kolom D
    $dataIdentitas = [
        5  => $warga->nama_lengkap ?? $warga->nama,
        6  => $warga->nik,
        7  => $warga->tanggal_lahir
                ? Carbon::parse($warga->tanggal_lahir)->translatedFormat('d F Y')
                : '-',
        8  => $warga->alamat,
        9  => $warga->no_hp,
        10 => $warga->status_nikah,
        11 => $warga->pekerjaan,
        12 => sprintf('%s/%s/%s', $warga->dusun ?? '-', $warga->rt ?? '-', $warga->rw ?? '-'),
        13 => $warga->kecamatan,
        14 => $warga->desa,
    ];

    foreach ($dataIdentitas as $row => $value) {

        // merge kolom D–F
        $sheet->mergeCells("D{$row}:F{$row}");
        $sheet->setCellValue("D{$row}", $value ?? '-');

        $sheet->getStyle("D{$row}:F{$row}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
    }

    // ==============================
    // Jenis kelamin (dipindah ke G5)
    // ==============================
    $jenisRaw = trim($warga->jenis_kelamin ?? '');
    $rt = new RichText();
    $rt->createTextRun('( ');
    $textL = $rt->createTextRun('Laki-laki');

    if (strcasecmp($jenisRaw, 'Laki-laki') === 0 || $jenisRaw === 'L') {
        $textL->getFont()->getColor()->setARGB(Color::COLOR_RED);
    }

    $rt->createTextRun(' / ');
    $textP = $rt->createTextRun('Perempuan');

    if (strcasecmp($jenisRaw, 'Perempuan') === 0 || $jenisRaw === 'P') {
        $textP->getFont()->getColor()->setARGB(Color::COLOR_RED);
    }

    $rt->createTextRun(' )');

    $sheet->setCellValue('G5', $rt);

    // ==============================
    // Umur (dipindah ke G6)
    // ==============================
    $tahun = $warga->tanggal_lahir
        ? Carbon::parse($warga->tanggal_lahir)->diff(now())->y
        : 0;

    $sheet->setCellValue('G6', "( {$tahun} Tahun )");

    $sheet->getStyle('G5:G6')->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
        ->setVertical(Alignment::VERTICAL_CENTER);

    // =====================================================================
    // 2. BLOK RIWAYAT KELUARGA / DIRI / PERILAKU (digeser 1 kolom → mulai Q)
    // =====================================================================

    // ---------------- RIWAYAT KELUARGA ----------------
    $sheet->mergeCells('Q5:R6');
    $sheet->setCellValue('Q5', "Riwayat Keluarga\n(lingkari jika ada)");
    $sheet->getStyle('Q5:R6')->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
        ->setVertical(Alignment::VERTICAL_TOP)
        ->setWrapText(true);

    // pasangan merge baru (S–T, U–V, W–X, Y–Z, AA–AB, AC–AD)
    $riwayatCols = [
        ['S','T'],
        ['U','V'],
        ['W','X'],
        ['Y','Z'],
        ['AA','AB'],
        ['AC','AD'],
    ];

    // teks riwayat
    $riwayatKeluargaItems = [
        'a. Hipertensi',
        'b. DM',
        'c. Stroke',
        'd. Jantung',
        'f. Kanker',
        'g. Kolesterol Tinggi',
    ];

    $row = 5;
    foreach ($riwayatCols as $i => [$col1, $col2]) {
        if (!isset($riwayatKeluargaItems[$i])) break;

        $sheet->mergeCells("{$col1}{$row}:{$col2}{$row}");
        $sheet->setCellValue("{$col1}{$row}", $riwayatKeluargaItems[$i]);
        $sheet->getStyle("{$col1}{$row}:{$col2}{$row}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
    }

    // ---------------- RIWAYAT DIRI SENDIRI ----------------
    $sheet->mergeCells('Q7:R8');
    $sheet->setCellValue('Q7', "Riwayat Diri Sendiri\n(lingkari jika ada)");
    $sheet->getStyle('Q7:R8')->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
        ->setVertical(Alignment::VERTICAL_TOP)
        ->setWrapText(true);

    $riwayatDiriItems = [
        'a. Hipertensi',
        'b. DM',
        'c. Stroke',
        'd. Jantung',
        'f. Kanker',
        'g. Kolesterol Tinggi',
    ];

    $row = 7;
    foreach ($riwayatCols as $i => [$col1, $col2]) {
        if (!isset($riwayatDiriItems[$i])) break;

        $sheet->mergeCells("{$col1}{$row}:{$col2}{$row}");
        $sheet->setCellValue("{$col1}{$row}", $riwayatDiriItems[$i]);
        $sheet->getStyle("{$col1}{$row}:{$col2}{$row}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
    }

    // ---------------- PERILAKU BERISIKO ----------------
    $sheet->mergeCells('Q9:R12');
    $sheet->setCellValue('Q9', "Perilaku Berisiko Diri Sendiri\n(lingkari jika ada)");
    $sheet->getStyle('Q9:R12')->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
        ->setVertical(Alignment::VERTICAL_TOP)
        ->setWrapText(true);

    // merge isi perilaku (S–V menjadi T–W → digeser 1 jadi S–V)
    foreach ([9,10,11,12] as $row) {
        $sheet->mergeCells("S{$row}:V{$row}");
    }

    // isi perilaku
    $sheet->setCellValue('S9',  "a. Merokok");
    $sheet->setCellValue('S10', "b. Konsumsi Tinggi Gula");
    $sheet->setCellValue('S11', "c. Konsumsi Tinggi Garam");
    $sheet->setCellValue('S12', "d. Konsumsi Tinggi Lemak");

    $sheet->getStyle("S9:V12")->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
        ->setVertical(Alignment::VERTICAL_TOP)
        ->setWrapText(true);

    // YA/TIDAK: X–Y → digeser menjadi Y–Z
    foreach ([9,10,11,12] as $row) {
        $sheet->mergeCells("Y{$row}:Z{$row}");
    }

    $sheet->setCellValue('Y9',  ': Ya/Tidak');
    $sheet->setCellValue('Y10', ': Ya/Tidak');
    $sheet->setCellValue('Y11', ': Ya/Tidak');
    $sheet->setCellValue('Y12', ': Ya/Tidak');

    $sheet->getStyle('Y9:Z12')->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
        ->setVertical(Alignment::VERTICAL_CENTER)
        ->setWrapText(true);

    // styling per area baru
    $sheet->getStyle('Q5:AD12')->getFont()->setSize($fontSizeHeaderKecil);
    $sheet->getStyle('Q5:AD12')->getAlignment()
        ->setVertical(Alignment::VERTICAL_TOP)
        ->setWrapText(true);


    // =====================================================================
    // 3. HEADER TABEL AKS
    // =====================================================================
    $sheet->mergeCells('B16:AE17');
    $richText = new RichText();
    $richText->createTextRun("Aktifitas Kehidupan Sehari-hari (AKS)")->getFont()->setBold(true)->setSize(14);
    $richText->createTextRun("\n(Jika hasil perhitungan skor <20 atau termasuk kelompok Ringan, Sedang, Berat dan Total maka dilakukan rujuk ke Pustu/Puskesmas)")->getFont()->setSize($fontSizeHeaderKecil);
    $sheet->setCellValue('B16', $richText);
    $sheet->getStyle('B16:AE17')->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle('B16:AE17')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    // Kolom A: Tanggal
    $sheet->mergeCells("A16:A{$optionRowEnd}");
    $sheet->setCellValue("A16", "Waktu ke\nPosyandu\n(tanggal/bulan/tahun)");
    $sheet->getStyle("A16:A{$optionRowEnd}")
        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
    $sheet->getStyle("A16:A{$optionRowEnd}")
        ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    // BAB — 3 KOLOM (B, C, D)
    $sheet->mergeCells("B18:D19"); 
    $sheet->setCellValue("B18", "Mengendalikan rangsang Buang Air Besar (BAB)");
    $sheet->mergeCells("B{$optionRowStart}:B{$optionRowEnd}"); 
    $sheet->setCellValue("B{$optionRowStart}", "Skor 0\nTidak terkendali / tak teratur\n(perlu pencahar)");
    $sheet->mergeCells("C{$optionRowStart}:C{$optionRowEnd}"); 
    $sheet->setCellValue("C{$optionRowStart}", "Skor 1\nKadang-kadang tak terkendali\n(≥1x/minggu)");
    $sheet->mergeCells("D{$optionRowStart}:D{$optionRowEnd}"); 
    $sheet->setCellValue("D{$optionRowStart}", "Skor 2\nTerkendali & mandiri");

    // BAK (E-G)
    $sheet->mergeCells("E18:G19"); 
    $sheet->setCellValue("E18", "Mengendalikan rangsang Buang Air Kecil (BAK)");
    $sheet->mergeCells("E{$optionRowStart}:E{$optionRowEnd}"); 
    $sheet->setCellValue("E{$optionRowStart}", "Skor 0\nTidak terkendali / pakai kateter");
    $sheet->mergeCells("F{$optionRowStart}:F{$optionRowEnd}"); 
    $sheet->setCellValue("F{$optionRowStart}", "Skor 1\nKadang-kadang tak terkendali\n(≥1x/24 jam)");
    $sheet->mergeCells("G{$optionRowStart}:G{$optionRowEnd}"); 
    $sheet->setCellValue("G{$optionRowStart}", "Skor 2\nTerkendali & mandiri");

    // Membersihkan diri (H-I)
    $sheet->mergeCells("H18:I19"); 
    $sheet->setCellValue("H18", "Membersihkan diri\n(mencuci wajah, menyikat rambut, sikat gigi, dll)");
    $sheet->mergeCells("H{$optionRowStart}:H{$optionRowEnd}"); 
    $sheet->setCellValue("H{$optionRowStart}", "Skor 0\nButuh bantuan orang lain");
    $sheet->mergeCells("I{$optionRowStart}:I{$optionRowEnd}"); 
    $sheet->setCellValue("I{$optionRowStart}", "Skor 1\nMandiri");

    // Ke WC (J-L)
    $sheet->mergeCells("J18:L19"); 
    $sheet->setCellValue("J18", "Penggunaan WC (keluar masuk WC, melepas/memakai celana, cebok, menyiram)");
    $sheet->mergeCells("J{$optionRowStart}:J{$optionRowEnd}"); 
    $sheet->setCellValue("J{$optionRowStart}", "Skor 0\nTergantung orang lain");
    $sheet->mergeCells("K{$optionRowStart}:K{$optionRowEnd}"); 
    $sheet->setCellValue("K{$optionRowStart}", "Skor 1\nPerlu pertolongan pada beberapa kegiatan");
    $sheet->mergeCells("L{$optionRowStart}:L{$optionRowEnd}"); 
    $sheet->setCellValue("L{$optionRowStart}", "Skor 2\nMandiri");

    // Makan (M-O)
    $sheet->mergeCells("M18:O19"); 
    $sheet->setCellValue("M18", "Makan minum\n(jika makan harus berupa potongan, dianggap dibantu)");
    $sheet->mergeCells("M{$optionRowStart}:M{$optionRowEnd}"); 
    $sheet->setCellValue("M{$optionRowStart}", "Skor 0\nTidak mampu");
    $sheet->mergeCells("N{$optionRowStart}:N{$optionRowEnd}"); 
    $sheet->setCellValue("N{$optionRowStart}", "Skor 1\nPerlu bantuan (dipotong / disuapi)");
    $sheet->mergeCells("O{$optionRowStart}:O{$optionRowEnd}"); 
    $sheet->setCellValue("O{$optionRowStart}", "Skor 2\nMandiri");

    // Bergerak (P-S)
    $sheet->mergeCells("P18:S19"); 
    $sheet->setCellValue("P18", "Bergerak dari kursi roda ke tempat tidur dan sebaliknya (termasuk duduk di tempat tidur)");
    $sheet->mergeCells("P{$optionRowStart}:P{$optionRowEnd}"); 
    $sheet->setCellValue("P{$optionRowStart}", "Skor 0\nTidak mampu");
    $sheet->mergeCells("Q{$optionRowStart}:Q{$optionRowEnd}"); 
    $sheet->setCellValue("Q{$optionRowStart}", "Skor 1\nButuh bantuan 2 orang");
    $sheet->mergeCells("R{$optionRowStart}:R{$optionRowEnd}"); 
    $sheet->setCellValue("R{$optionRowStart}", "Skor 2\nButuh bantuan 1 orang");
    $sheet->mergeCells("S{$optionRowStart}:S{$optionRowEnd}"); 
    $sheet->setCellValue("S{$optionRowStart}", "Skor 3\nMandiri");

    // Berjalan (T-W)
    $sheet->mergeCells("T18:W19"); 
    $sheet->setCellValue("T18", "Berjalan di tempat rata (atau jika tidak bisa berjalan, menjalankan kursi roda)");
    $sheet->mergeCells("T{$optionRowStart}:T{$optionRowEnd}"); 
    $sheet->setCellValue("T{$optionRowStart}", "Skor 0\nTidak mampu");
    $sheet->mergeCells("U{$optionRowStart}:U{$optionRowEnd}"); 
    $sheet->setCellValue("U{$optionRowStart}", "Skor 1\nHanya dengan kursi roda");
    $sheet->mergeCells("V{$optionRowStart}:V{$optionRowEnd}"); 
    $sheet->setCellValue("V{$optionRowStart}", "Skor 2\nBerjalan dengan bantuan 1 orang");
    $sheet->mergeCells("W{$optionRowStart}:W{$optionRowEnd}"); 
    $sheet->setCellValue("W{$optionRowStart}", "Skor 3\nMandiri (boleh pakai tongkat)");

    // Berpakaian (X-Z)
    $sheet->mergeCells("X18:Z19"); 
    $sheet->setCellValue("X18", "Berpakaian (memakai baju, ikat sepatu, dsb)");
    $sheet->mergeCells("X{$optionRowStart}:X{$optionRowEnd}"); 
    $sheet->setCellValue("X{$optionRowStart}", "Skor 0\nTergantung orang lain");
    $sheet->mergeCells("Y{$optionRowStart}:Y{$optionRowEnd}"); 
    $sheet->setCellValue("Y{$optionRowStart}", "Skor 1\nSebagian dibantu");
    $sheet->mergeCells("Z{$optionRowStart}:Z{$optionRowEnd}"); 
    $sheet->setCellValue("Z{$optionRowStart}", "Skor 2\nMandiri");

    // Naik tangga (AA-AC)
    $sheet->mergeCells("AA18:AC19"); 
    $sheet->setCellValue("AA18", "Naik turun 1 lantai tangga");
    $sheet->mergeCells("AA{$optionRowStart}:AA{$optionRowEnd}"); 
    $sheet->setCellValue("AA{$optionRowStart}", "Skor 0\nTidak mampu");
    $sheet->mergeCells("AB{$optionRowStart}:AB{$optionRowEnd}"); 
    $sheet->setCellValue("AB{$optionRowStart}", "Skor 1\nButuh bantuan + pegangan");
    $sheet->mergeCells("AC{$optionRowStart}:AC{$optionRowEnd}"); 
    $sheet->setCellValue("AC{$optionRowStart}", "Skor 2\nMandiri");

    // Mandi (AD-AE)
    $sheet->mergeCells("AD18:AE19"); 
    $sheet->setCellValue("AD18", "Mandi sendiri\n(masuk-keluar kamar mandi)");
    $sheet->mergeCells("AD{$optionRowStart}:AD{$optionRowEnd}"); 
    $sheet->setCellValue("AD{$optionRowStart}", "Skor 0\nTergantung orang lain");
    $sheet->mergeCells("AE{$optionRowStart}:AE{$optionRowEnd}"); 
    $sheet->setCellValue("AE{$optionRowStart}", "Skor 1\nMandiri");

    // Tingkat ketergantungan (AF)
    $sheet->mergeCells("AF16:AF19"); 
    $sheet->setCellValue("AF16", "Tingkat Ketergantungan\n(M/R/S/B/T)");
    $sheet->getStyle("AF16:AF19")->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER)
        ->setWrapText(true);
    $sheet->getStyle("AF16:AF19")->getBorders()->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN);
    $sheet->mergeCells("AF20:AF21"); 
    $sheet->setCellValue("AF20", "Mandiri (M=20)\nRingan (R=12-19)\nSedang (S=9-11)\nBerat (B=5-8)\nTotal (T=0-4)");

    // Edukasi & Rujuk AKS (AG-AH)
    $sheet->mergeCells("AG16:AG{$optionRowEnd}"); 
    $sheet->setCellValue("AG16", "Edukasi");
    $sheet->getStyle("AG16:AG{$optionRowEnd}")->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER)
        ->setWrapText(true);
    $sheet->getStyle("AG16:AG{$optionRowEnd}")->getBorders()->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN);

    $sheet->mergeCells("AH16:AH{$optionRowEnd}"); 
    $sheet->setCellValue("AH16", "Rujuk\nPustu/Puskesmas");
    $sheet->getStyle("AH16:AH{$optionRowEnd}")->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER)
        ->setWrapText(true);
    $sheet->getStyle("AH16:AH{$optionRowEnd}")->getBorders()->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN);

    // Warna background
    $colorHeader   = 'FFD7E1F3';
    $colorTanggal  = 'FFFCE2D2';
    $colorEdukasi  = 'FFCCCCFF';

    $sheet->getStyle('A16:A21')
        ->getFill()->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB($colorTanggal);
    $sheet->getStyle("AG16:AG21")
        ->getFill()->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB($colorEdukasi);

    $headerRanges = [
        'B16:AE17', 'B18:D21', 'E18:G21', 'H18:I21', 'J18:L21', 'M18:O21',
        'P18:S21', 'T18:W21', 'X18:Z21', 'AA18:AC21', 'AD18:AE21',
        'AF16:AF21', 'AF20:AF21', 'AH16:AH21'
    ];
    foreach ($headerRanges as $range) {
        $sheet->getStyle($range)
            ->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB($colorHeader);
    }

    // =====================================================================
    // LEBAR KOLOM
    // =====================================================================
    $columnWidths = [
        'A'  => 20,
        'B'  => 10, 'C'  => 10, 'D'  => 10,
        'E'  => 9,  'F'  => 9,  'G'  => 9,  'H'  => 9,  'I'  => 9,
        'J'  => 9,  'K'  => 9,  'L'  => 9,  'M'  => 9,  'N'  => 9,
        'O'  => 9,  'P'  => 9,  'Q'  => 9,  'R'  => 9,  'S'  => 9,
        'T'  => 9,  'U'  => 9,  'V'  => 9,  'W'  => 9,  'X'  => 9,
        'Y'  => 9,  'Z'  => 9,
        'AA' => 9, 'AB' => 9, 'AC' => 9, 'AD' => 9, 'AE' => 9,
        'AF' => 14, 'AG' => 10, 'AH' => 10,
        // kalau mau atur lebar SKILAS bisa tambah AI–AY di sini
    ];
    foreach ($columnWidths as $col => $width) {
        $sheet->getColumnDimension($col)->setWidth($width);
    }

    // Default row height untuk semua baris
    $sheet->getDefaultRowDimension()->setRowHeight(22);

    // Override untuk baris tertentu
    $sheet->getRowDimension(17)->setRowHeight(40); // baris skor / judul tengah
    $sheet->getRowDimension(20)->setRowHeight(70); // baris skor / judul tengah
    $sheet->getRowDimension(21)->setRowHeight(60); // baris "Ya / Tidak" agar tidak terpotong

    // =====================================================================
    // 4. HEADER SKILAS — DIGESER 1 KOLOM KE KANAN (MULAI AJ)
    // =====================================================================

    // =====================================================================
    // HEADER UTAMA & IDENTITAS SKILAS
    // =====================================================================
    $sheet->setCellValue('AJ1', 'Belakang');
    $sheet->mergeCells('AJ1:AY1');

    // FONT
    $sheet->getStyle('AJ1:AY1')->getFont()
        ->setSize($fontSizeDepanBelakang)
        ->setBold(true)
        ->getColor()->setARGB('FFFFFFFF'); // putih

    // ALIGNMENT
    $sheet->getStyle('AJ1:AY1')->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);

    // BACKGROUND
    $sheet->getStyle('AJ1:AY1')->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FF00B050'); // hijau 00B050


    $sheet->setCellValue('AJ2', 'KARTU BANTU PEMERIKSAAN LANSIA (≥60 Tahun)');
    $sheet->mergeCells('AJ2:AY2');
    $sheet->getStyle('AJ2:AY2')->getFont()->setSize($fontSizeHeaderUtama)->setBold(true);
    $sheet->getStyle('AJ2:AY2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->setCellValue('AJ3', 'POSYANDU TAMAN CIPULIR ESTATE');
    $sheet->mergeCells('AJ3:AY3');
    $sheet->getStyle('AJ3:AY3')->getFont()->setSize($fontSizeHeaderUtama)->setBold(true);
    $sheet->getStyle('AJ3:AY3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Label identitas SKILAS
     $labelsSKILAS = [
        'AJ5'  => 'Nama',
        'AJ6'  => 'NIK',
        'AJ7'  => 'Tanggal Lahir',
        'AJ8'  => 'Alamat',
        'AJ9'  => 'No. HP',
        'AJ10' => 'Status Perkawinan',
        'AJ11' => 'Pekerjaan',
        'AJ12' => 'Dusun/RT/RW',
        'AJ13' => 'Kecamatan',
        'AJ14' => 'Desa/Kelurahan/Nagari',
    ];

    foreach ($labelsSKILAS as $cell => $text) {

        // ambil nomor baris, contoh: AJ5 → 5
        $row = preg_replace('/\D/', '', $cell);

        // merge AJ–AL
        $sheet->mergeCells("AJ{$row}:AL{$row}");
        $sheet->setCellValue("AJ{$row}", $text);

        $sheet->getStyle("AJ{$row}:AL{$row}")->getFont()
            ->setSize($fontSizeProfil)
            ->setBold(true);

        $sheet->getStyle("AJ{$row}:AL{$row}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
    }

    // ==============================
    // Titik dua pindah ke AM
    // ==============================
    foreach (['AM5','AM6','AM7','AM8','AM9','AM10','AM11','AM12','AM13','AM14'] as $cell) {
        $sheet->setCellValue($cell, ':');
        $sheet->getStyle($cell)->getFont()->setSize(12);
        $sheet->getStyle($cell)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER);
    }

    // ==============================
    // Isi identitas → merge AN–AP
    // ==============================
    $dataIdentitasSkilas = [
        5  => $warga->nama_lengkap ?? $warga->nama,
        6  => $warga->nik,
        7  => $warga->tanggal_lahir
                ? Carbon::parse($warga->tanggal_lahir)->translatedFormat('d F Y')
                : '-',
        8  => $warga->alamat,
        9  => $warga->no_hp,
        10 => $warga->status_nikah,
        11 => $warga->pekerjaan,
        12 => sprintf('%s/%s/%s', $warga->dusun ?? '-', $warga->rt ?? '-', $warga->rw ?? '-'),
        13 => $warga->kecamatan,
        14 => $warga->desa,
    ];

    foreach ($dataIdentitasSkilas as $row => $value) {
        $sheet->mergeCells("AN{$row}:AP{$row}");
        $sheet->setCellValue("AN{$row}", $value ?? '-');

        $sheet->getStyle("AN{$row}:AP{$row}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
    }

    // ==============================
    // Jenis kelamin di AQ5
    // ==============================
    $jenisRaw = trim($warga->jenis_kelamin ?? '');
    $rt = new RichText();
    $rt->createTextRun('( ');
    $textL = $rt->createTextRun('Laki-laki');
    if ($jenisRaw === 'Laki-laki' || $jenisRaw === 'L') {
        $textL->getFont()->getColor()->setARGB(Color::COLOR_RED);
    }
    $rt->createTextRun(' / ');
    $textP = $rt->createTextRun('Perempuan');
    if ($jenisRaw === 'Perempuan' || $jenisRaw === 'P') {
        $textP->getFont()->getColor()->setARGB(Color::COLOR_RED);
    }
    $rt->createTextRun(' )');

    $sheet->setCellValue('AQ5', $rt);

    // ==============================
    // Umur di AQ6
    // ==============================
    $tahun = $warga->tanggal_lahir
        ? Carbon::parse($warga->tanggal_lahir)->diff(now())->y
        : 0;

    $sheet->setCellValue("AQ6", "( {$tahun} Tahun )");
    $sheet->getStyle('AQ5:AQ6')->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
        ->setVertical(Alignment::VERTICAL_CENTER);

    $steps = [
        'AF5'  => ['text' => ': Disi langkah 1', 'color' => 'FFFCE2D2'],
        'AF6'  => ['text' => ': Disi langkah 2', 'color' => 'FFFFE79B'],
        'AF7' => ['text' => ': Disi langkah 3', 'color' => 'FFFFFFCC'],
        'AF8' => ['text' => ': Disi langkah 4', 'color' => 'FFD7E1F3'],
        'AF9' => ['text' => ': Disi langkah 5', 'color' => 'FFCCCCFF'],
    ];
    foreach ($steps as $cell => $v) {
        $sheet->setCellValue($cell, $v['text']);
        $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($v['color']);
    }
    $sheet->getStyle('P5:AA12')->getFont()->setSize($fontSizeHeaderKecil);
    $sheet->getStyle('P5:AA12')->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);

    // Kolom Waktu ke Posyandu untuk SKILAS (SETELAH AH, tapi sekarang di AJ,
    // karena AI dibiarkan kosong sebagai pemisah visual)
    $sheet->mergeCells("AJ16:AJ21");
    $sheet->setCellValue("AJ16", "Waktu ke\nPosyandu\n(tanggal/bulan/tahun)");
    $sheet->getStyle("AJ16:AJ21")->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
        ->setWrapText(true);
    $sheet->getStyle("AJ16:AJ21")->getBorders()->getAllBorders()
        ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

    // Judul SKILAS (geser dari AI–AX ke AJ–AY)
    $sheet->mergeCells('AK16:AV17');
    $richText = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
    $judul = "SKILAS - Skrining Risiko Lansia";
    $rtBold = $richText->createTextRun($judul);
    $rtBold->getFont()->setBold(true)->setSize(14);

    $subjudul = "\nJika terdapat 1 atau lebih jawaban 'YA' → Wajib rujuk ke Puskesmas / RS";
    $rtNormal = $richText->createTextRun($subjudul);
    $rtNormal->getFont()->setBold(false)->setSize($fontSizeHeaderKecil);
    
    $sheet->setCellValue('AK16', $richText);


    $sheet->getStyle('AJ16:AY17')->getAlignment()
        ->setWrapText(true)
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

    $sheet->getStyle('AJ16:AY17')->getBorders()->getAllBorders()
        ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

    // ------------------------------
    // Baris 19: kelompok indikator
    // ------------------------------

    // Orientasi waktu / tempat (AK–AL)
    $sheet->mergeCells('AK18:AL19');
    $sheet->setCellValue('AK18', 'Orientasi waktu / tempat');
    $sheet->getStyle('AK18:AL19')->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet->getStyle('AK18:AL19')->getBorders()->getAllBorders()
        ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

    // Kognitif
    $sheet->mergeCells('AK18:AL19');
    $sheet->setCellValue('AK18', 'Penurunan Kognitif');
    $sheet->getStyle('AK18:AL19')->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet->getStyle('AK18:AL19')->getBorders()->getAllBorders()
        ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

    // Keterbatasan mobilisasi (AM)
    $sheet->mergeCells('AM18:AM19');
    $sheet->setCellValue('AM18', 'Keterbatasan Mobilisasi');
    $sheet->getStyle('AM18')->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet->getStyle('AM18')->getBorders()->getAllBorders()
        ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

    // Malnutrisi (AN–AP)
    $sheet->mergeCells('AN18:AP19');
    $sheet->setCellValue('AN18', 'Malnutrisi');
    $sheet->getStyle('AN18:AP19')->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet->getStyle('AN18:AP19')->getBorders()->getAllBorders()
        ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

    // Gangguan penglihatan (AQ–AR)
    $sheet->mergeCells('AQ18:AR19');
    $sheet->setCellValue('AQ18', 'Gangguan Penglihatan');
    $sheet->getStyle('AQ18:AR19')->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet->getStyle('AQ18:AR19')->getBorders()->getAllBorders()
        ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

    // Gangguan pendengaran (AS–AT)
    $sheet->mergeCells('AS18:AT19');
    $sheet->setCellValue('AS18', 'Gangguan Pendengaran');
    $sheet->getStyle('AS18:AT19')->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet->getStyle('AS18:AT19')->getBorders()->getAllBorders()
        ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

    // Gejala depresi (AU–AV)
    $sheet->mergeCells('AU18:AV19');
    $sheet->setCellValue('AU18', "Gejala Depresi\n(dalam 2 minggu terakhir)");
    $sheet->getStyle('AU18:AV19')->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
        ->setWrapText(true);
    $sheet->getStyle('AU18:AV19')->getBorders()->getAllBorders()
        ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

    // Imunisasi COVID (AW)
    $sheet->mergeCells('AW16:AW21');
    $sheet->setCellValue('AW16', "Imunisasi COVID");
    $sheet->getStyle('AW16:AW21')->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
        ->setWrapText(true);
    $sheet->getStyle('AW16:AW21')->getBorders()->getAllBorders()
        ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

    // Edukasi (AX)
    $sheet->mergeCells('AX16:AX21');
    $sheet->setCellValue('AX16', "Edukasi");
    $sheet->getStyle('AX16:AX21')->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
        ->setWrapText(true);
    $sheet->getStyle('AX16:AX21')->getBorders()->getAllBorders()
        ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

    // Rujuk Pustu/Puskesmas (AY)
    $sheet->mergeCells('AY16:AY21');
    $sheet->setCellValue('AY16', "Rujuk Pustu/Puskesmas");
    $sheet->getStyle('AY16:AY21')->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
        ->setWrapText(true);
    $sheet->getStyle('AY16:AY21')->getBorders()->getAllBorders()
        ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

    // ------------------------------------------------------
    // Baris 20 & 21: ISI SEMUA KOLOM SKILAS SECARA MANUAL
    // ------------------------------------------------------

    // Baris 20 → judul per indikator
    $allSkilasHeaders = [
        'AK' => 'Orientasi waktu / tempat',
        'AL' => 'Mengulang 3 kata',
        'AM' => 'Tes berdiri dari kursi',
        'AN' => 'BB ↓ ≥3kg / 3 bulan',
        'AO' => 'Hilang nafsu makan',
        'AP' => 'LiLA < 21 cm',
        'AQ' => 'Masalah pada mata',
        'AR' => 'Tes melihat (huruf kecil)',
        'AS' => 'Tes bisik (1 meter)',  // akan di-merge dengan AT
        'AT' => '',                     // kanan merge
        'AU' => 'Perasaan sedih / tertekan',
        'AV' => 'Sedikit minat / kenikmatan',
        'AW' => 'Imunisasi COVID',
        'AX' => 'Catatan / edukasi',
        'AY' => 'Rujuk otomatis',
    ];

    // Baris 20 → judul indikator
    foreach ($allSkilasHeaders as $col => $title) {
        if ($col === 'AS') {
            $sheet->mergeCells("AS20:AT20");
            $sheet->setCellValue('AS20', $title);
            $range = 'AS20:AT20';
        } elseif ($col === 'AT') {
            continue; // sudah ikut merge AS
        } else {
            $range = "{$col}20";
            $sheet->setCellValue($range, $title);
        }

        if ($col !== 'AT') {
            $sheet->getStyle($range)
                ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
                ->setWrapText(true);
            $sheet->getStyle($range)->getBorders()->getAllBorders()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        }
    }

    // Baris 21 → keterangan "Ya / Tidak" dll
    foreach ($allSkilasHeaders as $col => $title) {
        $range = "{$col}21";

        if (in_array($col, ['AX', 'AY'])) {
            // kolom catatan & rujuk → biarkan kosong
            $sheet->setCellValue($range, '');
        } elseif ($col === 'AS') {
            // Tes bisik → Ya/Tidak
            $sheet->setCellValue($range, "Ya / Tidak");
        } elseif ($col === 'AT') {
            // Kolom sebelahnya → Tidak dapat dilakukan
            $sheet->setCellValue($range, "Tidak dapat dilakukan");
        } else {
            // Kolom-kolom indikator lain → Ya/Tidak
            $sheet->setCellValue($range, "Ya / Tidak");
        }

        // Styling semua kolom baris 21 (termasuk AT sekarang)
        $sheet->getStyle($range)
            ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle($range)->getFont()->setSize(10);
        $sheet->getStyle($range)->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
    }

    // Merge kolom ringkasan (AY20–AY21)
    $sheet->mergeCells("AY20:AY21");
    $sheet->getStyle("AY20:AY21")->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

    // // Warna background
    $colorHeaderSKILAS   = 'FFD7E1F3';
    $colorTanggalSKILAS  = 'FFFCE2D2';
    $colorEdukasiSKILAS  = 'FFCCCCFF';

    $sheet->getStyle('AJ16:AJ21')
        ->getFill()->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB($colorTanggalSKILAS);
    $sheet->getStyle("AX16:AX21")
        ->getFill()->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB($colorEdukasiSKILAS);

    $headerRangesSKILAS = [
        'AK16:AV17', 'AK18:AL21', 'AM18:AM21', 'AN18:AP21', 'AQ18:AR21', 'AS18:AT21', 'AU18:AV21',
        'P18:S21', 'T18:W21', 'X18:Z21', 'AA18:AC21', 'AD18:AE21',
        'AW16:AW21', 'AX20:AX21', 'AY16:AY21'
    ];
    foreach ($headerRangesSKILAS as $rangeSKILAS) {
        $sheet->getStyle($rangeSKILAS)
            ->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB($colorHeaderSKILAS);
    }

    // =====================================================================
    // 5. ISI DATA AKS + SKILAS
    // =====================================================================
    $row = $aksFirstDataRow;
    foreach ($periksas as $periksa) {
        $sheet->setCellValue("A{$row}", Carbon::parse($periksa->tanggal_periksa)->format('d-m-Y'));

        // AKS (TIDAK DIUBAH)
        $aksMap = [
            'B'  => 'aks_bab_s0_tidak_terkendali',
            'C'  => 'aks_bab_s1_kadang_tak_terkendali',
            'D'  => 'aks_bab_s2_terkendali',
            'E'  => 'aks_bak_s0_tidak_terkendali_kateter',
            'F'  => 'aks_bak_s1_kadang_1x24jam',
            'G'  => 'aks_bak_s2_mandiri',
            'H'  => 'aks_diri_s0_butuh_orang_lain',
            'I'  => 'aks_diri_s1_mandiri',
            'J'  => 'aks_wc_s0_tergantung_lain',
            'K'  => 'aks_wc_s1_perlu_beberapa_bisa_sendiri',
            'L'  => 'aks_wc_s2_mandiri',
            'M'  => 'aks_makan_s0_tidak_mampu',
            'N'  => 'aks_makan_s1_perlu_pemotongan',
            'O'  => 'aks_makan_s2_mandiri',
            'P'  => 'aks_bergerak_s0_tidak_mampu',
            'Q'  => 'aks_bergerak_s1_butuh_2orang',
            'R'  => 'aks_bergerak_s2_butuh_1orang',
            'S'  => 'aks_bergerak_s3_mandiri',
            'T'  => 'aks_jalan_s0_tidak_mampu',
            'U'  => 'aks_jalan_s1_kursi_roda',
            'V'  => 'aks_jalan_s2_bantuan_1orang',
            'W'  => 'aks_jalan_s3_mandiri',
            'X'  => 'aks_pakaian_s0_tergantung_lain',
            'Y'  => 'aks_pakaian_s1_sebagian_dibantu',
            'Z'  => 'aks_pakaian_s2_mandiri',
            'AA' => 'aks_tangga_s0_tidak_mampu',
            'AB' => 'aks_tangga_s1_butuh_bantuan',
            'AC' => 'aks_tangga_s2_mandiri',
            'AD' => 'aks_mandi_s0_tergantung_lain',
            'AE' => 'aks_mandi_s1_mandiri',
        ];

        foreach ($aksMap as $col => $field) {
            $val = $periksa->{$field} ?? 0;
            $sheet->setCellValue("{$col}{$row}", $val ? 1 : '');
        }

        $sheet->setCellValue("AF{$row}", $periksa->aks_kategori ?? '');
        $sheet->setCellValue("AG{$row}", $periksa->aks_edukasi ?? '');
        $sheet->setCellValue("AH{$row}", $periksa->aks_perlu_rujuk ? 'YA' : 'TIDAK');

        // Kolom AI dikosongkan sebagai pembatas (boleh diisi apa pun kalau mau)
        // Waktu ke Posyandu SKILAS sekarang di AJ
        $sheet->setCellValue("AJ{$row}", \Carbon\Carbon::parse($periksa->tanggal_periksa)->format('d-m-Y'));
        // kalau ada field khusus, misal $periksa->tanggal_skilas, ganti ke itu

        // SKILAS – 13 indikator + ringkasan (SEMUA DIGESER +1 KOLOM)
        $skMap = [
            'AK' => 'skil_orientasi_waktu_tempat',
            'AL' => 'skil_mengulang_ketiga_kata',
            'AM' => 'skil_tes_berdiri_dari_kursi',
            'AN' => 'skil_bb_berkurang_3kg_dalam_3bulan',
            'AO' => 'skil_hilang_nafsu_makan',
            'AP' => 'skil_lla_kurang_21cm',
            'AQ' => 'skil_masalah_pada_mata',
            'AR' => 'skil_tes_melihat',
            'AS' => 'skil_tes_bisik',
            'AT' => 'skil_tidak_dapat_dilakukan',
            'AU' => 'skil_perasaan_sedih_tertekan',
            'AV' => 'skil_sedikit_minat_atau_kenikmatan',
            'AW' => 'skil_imunisasi_covid',
        ];

        $totalYa = 0;
        foreach ($skMap as $col => $field) {
            $val = $periksa->{$field} ?? 0;
            if ($val) {
                $sheet->setCellValue("{$col}{$row}", 'Ya');
                $totalYa++;
            } else {
                $sheet->setCellValue("{$col}{$row}", 'Tidak');
            }
        }

        // Edukasi SKILAS (AX)
        $sheet->setCellValue("AX{$row}", $periksa->skil_edukasi ?? '');

        // Rujuk otomatis SKILAS (AY)
        $sheet->setCellValue("AY{$row}", $periksa->skil_rujuk_otomatis ? 'Ya' : 'Tidak');

        $row++;
    }
    $lastRow = $row - 1;

    // =====================================================================
    // BARIS 22 → NOMOR KOLOM AKS + SKILAS
    // =====================================================================

    // Kolom AKS: B–Z + AA–AH
    $aksCols = array_merge(
        range('A', 'Z'),
        $excelColumnRange('AA', 'AH')
    );

    // Kolom SKILAS: AJ–AY
    $skilasCols = $excelColumnRange('AJ', 'AY');

    $noAks = 1;
    foreach ($aksCols as $col) {
        $sheet->setCellValue("{$col}22", $noAks++);
    }

    // --- Nomor kolom SKILAS (mulai 1 lagi) ---
    $noSkilas = 1;
    foreach ($skilasCols as $col) {
        $sheet->setCellValue("{$col}22", $noSkilas++);
    }

    $sheet->getStyle("A22:AY22")->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

    $sheet->getRowDimension(22)->setRowHeight(22);
    $sheet->getStyle("A22:AH22")->getFont()->setBold(true);

    $sheet->getRowDimension(22)->setRowHeight(22);
    $sheet->getStyle("AJ22:AY22")->getFont()->setBold(true);

    // ==============================
    // BACKGROUND WARNA ABU2 (BFBFBF)
    // ==============================
    $sheet->getStyle("A22:AH22")->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setARGB("FFBFBFBF");

    $sheet->getStyle("AJ22:AY22")->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setARGB("FFBFBFBF");

    // =====================================================================
    // 6. STYLING AKHIR
    // =====================================================================
    $sheet->mergeCells("AI16:AI{$lastRow}");

    $sheet->getStyle("A{$headerTopRow}:AY{$lastRow}")
        ->getBorders()->getAllBorders()
        ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

    $sheet->getStyle("A{$headerTopRow}:AY{$lastRow}")->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
        ->setWrapText(true);

    // ==============================
    // KOLOM AI → Pembatas (warna hijau sampai lastRow saja)
    // ==============================

    // Atur lebar pembatas
    $sheet->getColumnDimension('AI')->setWidth(2); // ubah sesuai kebutuhan

    // Background hijau hanya sampai baris terakhir tabel
    $sheet->getStyle("AI1:AI{$lastRow}")->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FF00B050');

    // non-autoSize semua kolom sampai AY
    foreach (array_merge(range('A','Z'), [
        'AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL',
        'AM','AN','AO','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY'
    ]) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(false);
    }

    // =====================================================================
    // 7. DOWNLOAD
    // =====================================================================
    $filename = "Lansia_AKS_SKILAS_{$warga->nik}.xlsx";
    $writer = new Xlsx($spreadsheet);

    return response()->streamDownload(function () use ($writer) {
        $writer->save('php://output');
    }, $filename, [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'Cache-Control' => 'max-age=0',
    ]);
}


    public function index()
    {
        return view('page.lansia.index');
    }

    public function data()
    {
        $lansia = Warga::with('pemeriksaanLansiaTerakhir')
            ->whereRaw('TIMESTAMPDIFF(YEAR, tanggal_lahir, CURDATE()) >= 60')
            ->select('id', 'nik', 'nama', 'tanggal_lahir')
            ->get();

        $data = $lansia->map(function ($w) {

            // -----------------------------
            // Hitung umur (tahun & bulan)
            // -----------------------------
            $lahir = $w->tanggal_lahir ? Carbon::parse($w->tanggal_lahir) : null;

            if ($lahir) {
                $diff  = $lahir->diff(now());
                $tahun = $diff->y;
                $bulan = $diff->m;
                $umur  = $tahun > 0 ? "{$tahun} thn {$bulan} bln" : "{$bulan} bln";
            } else {
                $umur = '-';
            }

            // -----------------------------
            // Ambil pemeriksaan terakhir
            // -----------------------------
            $p = $w->pemeriksaanLansiaTerakhir;

            // -----------------------------
            // Hitung nilai SKILAS positif
            // -----------------------------
            $skilasPositif = 0;

            if ($p) {
                $skilasPositif = collect($p->getAttributes())
                    ->filter(function ($value, $key) {
                        return str_starts_with($key, 'skil_') 
                            && !in_array($key, [
                                'skil_rujuk_otomatis',
                                'skil_rujuk_manual',
                                'skil_edukasi',
                                'skil_catatan'
                            ])
                            && $value == 1;
                    })
                    ->count();
            }

            // -----------------------------
            // Return API row untuk DataTables
            // -----------------------------
            return [
                'id'          => $w->id,
                'nik'         => $w->nik,
                'nama'        => $w->nama,
                'umur'        => $umur,

                'terakhir'    => $p
                    ? Carbon::parse($p->tanggal_periksa)->translatedFormat('d F Y')
                    : '<span class="text-red-600 font-bold">Belum pernah diperiksa</span>',

                'aks_total_skor' => $p?->aks_total_skor ?? '-',
                'aks_kategori'   => $p?->aks_kategori ?? '-',

                'skilas_positif' => $skilasPositif > 0
                    ? '<span class="text-red-600 font-bold">+' . $skilasPositif . '</span>'
                    : '<span class="text-gray-500">-</span>',

                'perlu_rujuk' => $p && (
                    $p->aks_perlu_rujuk ||
                    $p->skil_rujuk_otomatis ||
                    $p->skil_rujuk_manual
                ),

                'periksa_id' => $p?->id,
            ];
        });

        return response()->json([
            'data'         => $data,
            'total_lansia' => $lansia->count(),
        ]);
    }


    // Form Create (fragment)
    public function form(Warga $warga)
    {
        if (!$warga->tanggal_lahir || Carbon::parse($warga->tanggal_lahir)->age < 60) {
            return response()->json(['error' => 'Hanya untuk lansia usia ≥60 tahun'], 403);
        }

        $html = view('page.lansia.form', compact('warga'))->render();
        return response($html)->header('Content-Type', 'text/html');
    }

   // ================== DETAIL RIWAYAT ==================
    public function riwayat(Warga $warga)
    {
        $periksas = $warga->pemeriksaanLansiaAll()->get();

        $html = view('page.lansia.riwayat', compact('warga', 'periksas'))->render();

        return response($html)->header('Content-Type', 'text/html');
    }

    // ================== EDIT PEMERIKSAAN ==================
    public function edit(Warga $warga, $periksa)
    {
        // Ambil data pemeriksaan berdasarkan ID
        $lansia = PemeriksaanLansia::where('warga_id', $warga->id)
                                    ->where('id', $periksa)
                                    ->firstOrFail();

        $html = view('page.lansia.form', compact('warga', 'lansia'))->render();

        return response($html)->header('Content-Type', 'text/html');
    }

    // FUNGSI INI YANG DIPAKAI OLEH STORE & UPDATE
    private function simpanData(Request $request, PemeriksaanLansia $lansia = null)
    {
        $data = $request->all();

        // === AKS: ambil semua checkbox yang dicentang ===
        $aksFields = [
            'bab_s0_tidak_terkendali', 'bab_s1_kadang_tak_terkendali',
            'bak_s0_tidak_terkendali_kateter', 'bak_s1_kadang_1x24jam', 'bak_s2_mandiri',
            'diri_s0_butuh_orang_lain', 'diri_s1_mandiri',
            'wc_s0_tergantung_lain', 'wc_s1_perlu_beberapa_bisa_sendiri', 'wc_s2_mandiri',
            'makan_s0_tidak_mampu', 'makan_s1_perlu_pemotongan', 'makan_s2_mandiri',
            'bergerak_s0_tidak_mampu', 'bergerak_s1_butuh_2orang', 'bergerak_s2_butuh_1orang', 'bergerak_s3_mandiri',
            'jalan_s0_tidak_mampu', 'jalan_s1_kursi_roda', 'jalan_s2_bantuan_1orang', 'jalan_s3_mandiri',
            'pakaian_s0_tergantung_lain', 'pakaian_s1_sebagian_dibantu', 'pakaian_s2_mandiri',
            'tangga_s0_tidak_mampu', 'tangga_s1_butuh_bantuan', 'tangga_s2_mandiri',
            'mandi_s0_tergantung_lain', 'mandi_s1_mandiri',
        ];

        foreach ($aksFields as $field) {
            $data["aks_{$field}"] = $request->has("aks_{$field}");
        }

        // === SKILAS: semua field skil_* ===
        $skilFields = [
            'orientasi_waktu_tempat', 'mengulang_ketiga_kata', 'tes_berdiri_dari_kursi',
            'bb_berkurang_3kg_dalam_3bulan', 'hilang_nafsu_makan', 'lla_kurang_21cm',
            'masalah_pada_mata', 'tes_melihat', 'perasaan_sedih_tertekan',
            'sedikit_minat_atau_kenikmatan', 'imunisasi_covid'
        ];

        foreach ($skilFields as $f) {
            $data["skil_{$f}"] = $request->has("skil_{$f}");
        }

        // Tes bisik khusus
        $data['skil_tes_bisik'] = $request->has('skil_tes_bisik'); // Ya
        $data['skil_tidak_dapat_dilakukan'] = $request->has('skil_tidak_dapat_dilakukan');

        // Tanggal periksa
        $data['tanggal_periksa'] = $request->tanggal_periksa;

        // Warga ID
        $data['warga_id'] = $request->warga_id;

        // Simpan
        if ($lansia) {
            $lansia->update($data); // $data pasti array
        } else {
            $lansia = PemeriksaanLansia::create($data);
        }

        return $lansia;
    }

    public function store(Request $request)
    {
        $this->simpanData($request);

        return response()->json([
            'success' => true,
            'message' => 'Pemeriksaan berhasil disimpan!'
        ]);
    }

    public function update(Request $request, PemeriksaanLansia $lansia)
    {
        $this->simpanData($request, $lansia);

        return response()->json([
            'success' => true,
            'message' => 'Pemeriksaan berhasil diperbarui!'
        ]);
    }

    public function destroy(PemeriksaanLansia $lansia)
    {
        $lansia->delete();
        return response()->json(['success' => true]);
    }
}
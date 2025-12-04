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

    public function form(Warga $warga)
    {
        if (!$warga->tanggal_lahir || Carbon::parse($warga->tanggal_lahir)->age < 60) {
            return response()->json(['error' => 'Hanya untuk lansia usia ≥60 tahun'], 403);
        }

        $html = view('page.lansia.form', compact('warga'))->render();
        return response($html)->header('Content-Type', 'text/html');
    }

    public function riwayat(Warga $warga)
    {
        $periksas = $warga->pemeriksaanLansiaAll()->get();

        $html = view('page.lansia.riwayat', compact('warga', 'periksas'))->render();

        return response($html)->header('Content-Type', 'text/html');
    }

    public function edit(Warga $warga, $periksa)
    {
        // Ambil data pemeriksaan berdasarkan ID
        $lansia = PemeriksaanLansia::where('warga_id', $warga->id)
                                    ->where('id', $periksa)
                                    ->firstOrFail();

        $html = view('page.lansia.form', compact('warga', 'lansia'))->render();

        return response($html)->header('Content-Type', 'text/html');
    }

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

    public function exportLansiaExcelSatuan(Warga $warga)
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

    public function exportLansiaExcelSemua()
    {
        $wargas = Warga::with('pemeriksaanDewasaLansiaAll')
            ->whereHas('pemeriksaanDewasaLansiaAll')
            ->get();

        if ($wargas->isEmpty()) {
            abort(404, 'Belum ada data pemeriksaan');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Kartu Dewasa');

        $offset = 0;
        $jarakAntarKartu = 5; // baris kosong antar kartu

        foreach ($wargas as $warga) {
            $lastRow = $this->buildKartuSheetOffset($sheet, $warga, $offset);
            $offset = $lastRow + $jarakAntarKartu;
        }

        $filename = "Kartu_Pemeriksaan_Dewasa_Lansia_MULTI.xlsx";
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
    
    protected function buildKartuSheetOffset(Worksheet $sheet, Warga $warga, int $offsetRow = 0): int
    {
        // helper untuk geser baris
        $r = fn(int $n) => $n + $offsetRow;

        // ambil semua riwayat
        $periksas = $warga->pemeriksaanDewasaLansiaAll;

        if ($periksas->isEmpty()) {
            $sheet->setCellValue('A' . $r(2), 'Belum ada data pemeriksaan');
            return $r(5);
        }


        $fontSizeDefaultData   = 10; // ukuran teks umum (isi tabel, dll)
        $fontSizeHeaderUtama   = 16; // judul paling atas (A2, A3)
        $fontSizeHeaderBlok    = 14; // judul blok seperti "AKS", "SKILAS"
        $fontSizeHeaderKecil   = 11; // subjudul / teks penjelasan di header
        $fontSizeProfil        = 11; // subjudul / teks penjelasan di header
        $fontSizeDepanBelakang = 9; // subjudul / teks penjelasan di header
        // =====================================================================
        // LEBAR KOLOM
        // =====================================================================
        $columnWidths = [
            'A'  => 20,
            'B'  => 10, 'C'  => 10, 'D'  => 10,
            'E'  => 9,  'F'  => 9,  'G'  => 9,  'H'  => 9,  'I'  => 9,
            'J'  => 9,  'K'  => 9,  'L'  => 9,  'M'  => 9,  'N'  => 9,
            'O'  => 9,  'P'  => 9,  'Q'  => 9,  'R'  => 13,  'S'  => 13,
            'T'  => 13, 'U'  => 13, 'V'  => 9,  'W'  => 9,  'X'  => 9,
            'Y'  => 9,  'Z'  => 9,
            'AA' => 13, 'AB' => 13, 'AC' => 9,
            // kalau mau atur lebar SKILAS bisa tambah AI–AY di sini
        ];
        foreach ($columnWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $row1 = $r(1);
        $row2 = $r(2);
        $row3 = $r(3);

        // =====================================================================
        // 1. HEADER UTAMA & IDENTITAS AKS
        // =====================================================================

        // ALIGNMENT
        $sheet->getStyle("A$row1:AC$row1")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        // ROW 1 background hijau
        $sheet->getStyle("A{$row1}:AC{$row1}")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF00B050'); // hijau 00B050

        // ROW 2 — Judul besar
        $sheet->setCellValue("A{$row2}", "KARTU BANTU PEMERIKSAAN LANSIA (≥60 Tahun)");
        $sheet->mergeCells("A{$row2}:AC{$row2}");
        $sheet->getStyle("A{$row2}:AC{$row2}")->getFont()
            ->setSize($fontSizeHeaderUtama)
            ->setBold(true);
        $sheet->getStyle("A{$row2}:AC{$row2}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // ROW 3 — Subjudul
        $sheet->setCellValue("A{$row3}", "POSYANDU TAMAN CIPULIR ESTATE");
        $sheet->mergeCells("A{$row3}:AC{$row3}");
        $sheet->getStyle("A{$row3}:AC{$row3}")->getFont()
            ->setSize($fontSizeHeaderUtama)
            ->setBold(true);
        $sheet->getStyle("A{$row3}:AC{$row3}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        

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
            $rowLabel = preg_replace('/\D/', '', $cell);

            // merge A dan B per baris
            $sheet->mergeCells("A{$rowLabel}:B{$rowLabel}");
            $sheet->setCellValue("A{$rowLabel}", $text);

            $sheet->getStyle("A{$rowLabel}:B{$rowLabel}")->getFont()
                ->setSize($fontSizeProfil)
                ->setBold(true);

            $sheet->getStyle("A{$rowLabel}:B{$rowLabel}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);
        }

        $labelsB = [
            'B5'  => ':',
            'B6'  => ':',
            'B7'  => ':',
            'B8'  => ':',
            'B9'  => ':',
            'B10' => ':',
            'B11' => ':',
            'B12' => ':',
            'B13' => ':',
            'B14' => ':'
        ];

        foreach ($labelsB as $cell => $text) {
            $col = preg_replace('/[0-9]/', '', $cell);
            $row = (int) preg_replace('/\D/', '', $cell);
            $row = $r($row);
            $addr = $col . $row;

            $sheet->setCellValue($addr, $text);
            $style = $sheet->getStyle($addr);
            $style->getFont()->setSize(12)->setBold(false);
            $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }

        // ==================== ISI IDENTITAS ====================
        $dataIdentitas = [
            5  => $warga->nama,
            6  => $warga->nik,
            7  => $warga->tanggal_lahir,
            8  => $warga->alamat,
            9  => $warga->no_hp,
            10 => $warga->status_nikah,
            11 => $warga->pekerjaan,
            12 => sprintf('%s/%s/%s', $warga->dusun ?? '-', $warga->rt ?? '-', $warga->rw ?? '-'),
            13 => $warga->kecamatan,
            14 => $warga->desa
        ];

        foreach ($dataIdentitas as $row => $value) {
            $sheet->setCellValue('C' . $r($row), $value ?? '-');
        }

        // ==================== JENIS KELAMIN (RichText) ====================
        $row5 = $r(5);
        $row6 = $r(6);

        $jenisRaw = trim($warga->jenis_kelamin ?? '');
        $richText = new RichText();
        $richText->createTextRun('( ');
        $textL = $richText->createTextRun('Laki-laki');
        
        if (strcasecmp($jenisRaw, 'Laki-laki') === 0 || $jenisRaw === 'L') {
            $textL->getFont()->getColor()->setARGB(Color::COLOR_RED);
        }

        $richText->createTextRun(' / ');

        $textP = $richText->createTextRun('Perempuan');
        if (strcasecmp($jenisRaw, 'Perempuan') === 0 || $jenisRaw === 'P') {
            $textP->getFont()->getColor()->setARGB(Color::COLOR_RED);
        }

        $richText->createTextRun(' )');
        $sheet->setCellValue("D{$row5}", $richText);

        // ==================== UMUR ====================
        $tahun = $warga->tanggal_lahir
            ? helper_umur($warga->tanggal_lahir) : 0;

        $sheet->setCellValue("D{$row6}", "( {$tahun} Tahun )");

        $sheet->getStyle("D{$row6}:D{$row6}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER);

        // =====================================================================
        // 2. BLOK RIWAYAT KELUARGA / DIRI
        // =====================================================================
        // ---------------- RIWAYAT KELUARGA ----------------
        $sheet->mergeCells("Q{$row5}:R{$row6}");
        $sheet->setCellValue("Q{$row5}", "Riwayat Keluarga\n(lingkari jika ada)");
        $sheet->getStyle("Q{$row5}:R{$row6}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setWrapText(true);

        $riwayatKeluargaItems = [
            'a. Hipertensi',
            'b. DM',
            'c. Stroke',
            'd. Jantung',
            'f. Kanker',
            'g. Kolesterol Tinggi',
        ];
        // S-T (TIDAK MERGE)
        $sheet->setCellValue("S{$row5}", $riwayatKeluargaItems[0]);
        $sheet->getStyle("S{$row5}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        // U-V (TIDAK MERGE)
        $sheet->setCellValue("T{$row5}", $riwayatKeluargaItems[1]);
        $sheet->getStyle("T{$row5}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        // W-X (TIDAK MERGE)
        $sheet->setCellValue("U{$row5}", $riwayatKeluargaItems[2]);
        $sheet->getStyle("U{$row5}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        // Y-Z (TIDAK MERGE)
        $sheet->setCellValue("V{$row5}", $riwayatKeluargaItems[3]);
        $sheet->getStyle("V{$row5}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        // AA-AB (TIDAK MERGE)
        $sheet->setCellValue("W{$row5}", $riwayatKeluargaItems[4]);
        $sheet->getStyle("W{$row5}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        // AC-AD (HANYA INI MERGE)
        $sheet->mergeCells("X{$row5}:Y{$row5}");
        $sheet->setCellValue("X{$row5}", $riwayatKeluargaItems[5]);
        $sheet->getStyle("X{$row5}:Y{$row5}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
    


        $row7 = $r(7);
        $row8 = $r(8);

        // ---------------- RIWAYAT DIRI SENDIRI ----------------
        $sheet->mergeCells("Q{$row7}:R{$row8}");
        $sheet->setCellValue("Q{$row7}", "Riwayat Diri Sendiri\n(lingkari jika ada)");
        $sheet->getStyle("Q{$row7}:R{$row8}")->getAlignment()
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

        // S-T (tidak merge)
        $sheet->setCellValue("S{$row7}", $riwayatDiriItems[0]);
        $sheet->getStyle("S{$row7}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        // U-V (tidak merge)
        $sheet->setCellValue("T{$row7}", $riwayatDiriItems[1]);
        $sheet->getStyle("T{$row7}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        // W-X (tidak merge)
        $sheet->setCellValue("U{$row7}", $riwayatDiriItems[2]);
        $sheet->getStyle("U{$row7}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        // Y-Z (tidak merge)
        $sheet->setCellValue("V{$row7}", $riwayatDiriItems[3]);
        $sheet->getStyle("V{$row7}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        // AA-AB (tidak merge)
        $sheet->setCellValue("W{$row7}", $riwayatDiriItems[4]);
        $sheet->getStyle("W{$row7}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        // AC-AD (HANYA INI MERGE)
        $sheet->mergeCells("X{$row7}:Y{$row7}");
        $sheet->setCellValue("X{$row7}", $riwayatDiriItems[5]);
        $sheet->getStyle("X{$row7}:Y{$row7}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);


        $row9  = $r(9);
        $row10 = $r(10);
        $row11 = $r(11);
        $row12 = $r(12);

        // ---------------- PERILAKU BERISIKO ----------------
        $sheet->mergeCells("Q{$row9}:R{$row12}");
        $sheet->setCellValue("Q{$row9}", "Perilaku Berisiko Diri Sendiri\n(lingkari jika ada)");
        $sheet->getStyle("Q{$row9}:R{$row12}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setWrapText(true);

        // merge isi perilaku (S–V)
        foreach ([$row9, $row10, $row11, $row12] as $rowNum) {
            $sheet->mergeCells("S{$rowNum}:V{$rowNum}");
        }

        // isi perilaku
        $sheet->setCellValue("S{$row9}",  "a. Merokok");
        $sheet->setCellValue("S{$row10}", "b. Konsumsi Tinggi Gula");
        $sheet->setCellValue("S{$row11}", "c. Konsumsi Tinggi Garam");
        $sheet->setCellValue("S{$row12}", "d. Konsumsi Tinggi Lemak");

        $sheet->getStyle("S{$row9}:V{$row12}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setWrapText(true);

        // YA/TIDAK: merge Y–Z
        foreach ([$row9, $row10, $row11, $row12] as $rowNum) {
            $sheet->mergeCells("Y{$rowNum}:Z{$rowNum}");
        }


        $sheet->setCellValue("Y{$row9}", ': Ya/Tidak');
        $sheet->setCellValue("Y{$row10}", ': Ya/Tidak');
        $sheet->setCellValue("Y{$row11}", ': Ya/Tidak');
        $sheet->setCellValue("Y{$row12}", ': Ya/Tidak');

        $sheet->getStyle("Y{$row9}:Z{$row12}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        // styling per area baru (font size header kecil sudah ada di $fontSizeHeaderKecil)
        $sheet->getStyle("Q{$row5}:AD{$row12}")->getFont()->setSize($fontSizeHeaderKecil);
        $sheet->getStyle("Q{$row5}:AD{$row12}")->getAlignment()
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setWrapText(true);

        // Langkah-langkah di AB-AC baris 8..12
        $steps = [
            ['AB','AC', 8,  ': Diisi langkah 1', 'FFFCE2D2'],
            ['AB','AC', 9,  ': Diisi langkah 2', 'FFFFE79B'],
            ['AB','AC', 10, ': Diisi langkah 3', 'FFFFFFCC'],
            ['AB','AC', 11, ': Diisi langkah 4', 'FFD7E1F3'],
            ['AB','AC', 12, ': Diisi langkah 5', 'FFCCCCFF'],
        ];

        foreach ($steps as [$col1, $col2, $rowNum, $text, $color]) {
            // ambil variabel baris yang sesuai, tapi jangan timpa $r (closure)
            $targetRow = ${'row' . $rowNum};

            $sheet->mergeCells("{$col1}{$targetRow}:{$col2}{$targetRow}");
            $sheet->setCellValue("{$col1}{$targetRow}", $text);

            $sheet->getStyle("{$col1}{$targetRow}:{$col2}{$targetRow}")
                ->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB($color);

            $sheet->getStyle("{$col1}{$targetRow}:{$col2}{$targetRow}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);
        }

        $sheet->getStyle("P{$row5}:AA{$row12}")->getFont()->setSize(11);
        $sheet->getStyle("P{$row5}:AA{$row12}")->getAlignment()
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setWrapText(true);


        $row16 = $r(16); // Baris 16
        $row17 = $r(17); // Baris 17
        $row18 = $r(18); // Baris 18
        $row19 = $r(19); // Baris 19
        $row20 = $r(20); // Baris 20

        // ==================== HEADER ATAS (TABEL) ====================
        // judul Usia Dewasa dan Lansia
        $sheet->setCellValue("A{$row16}", 'Usia Dewasa dan Lansia');
        $sheet->mergeCells("A{$row16}:Z{$row16}");
        $sheet->getStyle("A{$row16}:Z{$row16}")->getFont()->setSize(16)->setBold(true);
        $sheet->getStyle("A{$row16}:Z{$row16}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A{$row16}:Z{$row16}")->getFill()
            ->setFillType(Fill::FILL_SOLID);

        // kolom A18
        $sheet->mergeCells("A{$row18}:A{$row20}");
        $sheet->setCellValue("A{$row18}", "Waktu ke\nPosyandu\n(tanggal/bulan/tahun)");
        $sheet->getStyle("A{$row18}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row18}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        // header besar baris 17
        $sheet->mergeCells("A{$row17}:N{$row17}");
        $sheet->setCellValue("A{$row17}", "Hasil Penimbangan / Pengukuran / Pemeriksaan\n(Jika hasil pemeriksaan Tekanan Darah/Gula Darah tergolong tinggi maka dirujuk ke Pustu/Puskesmas)");
        $sheet->getStyle("A{$row17}:N{$row17}")->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle("A{$row17}:N{$row17}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        $sheet->mergeCells("O{$row17}:V{$row17}");
        $sheet->setCellValue("O{$row17}", "Kuesioner PPOK/PUMA (Skoring) ≥ 40 Tahun dan merokok\n(jika sasaran menjawab dengan score >6 , maka sasaran dirujuk ke Pustu/Puskesmas)");
        $sheet->getStyle("O{$row17}:V{$row17}")->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle("O{$row17}:V{$row17}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        $sheet->mergeCells("W{$row17}:Z{$row17}");
        $sheet->setCellValue("W{$row17}", 'Hasil Wawancara Faktor Risiko PM');
        $sheet->getStyle("W{$row17}:Z{$row17}")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("W{$row17}:Z{$row17}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells("AA{$row16}:AA{$row20}");
        $sheet->setCellValue("AA{$row16}", "Wawancara Usia Dewasa\nyang menggunakan Alat Kontrasepsi\n(Pil/Kondom/Lainnya)\n(Ya/Tidak)");
        $sheet->getStyle("AA{$row16}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        $sheet->mergeCells("AB{$row16}:AB{$row20}");
        $sheet->setCellValue("AB{$row16}", "Edukasi");
        $sheet->getStyle("AB{$row16}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        $sheet->mergeCells("AC{$row16}:AC{$row20}");
        $sheet->setCellValue("AC{$row16}", "Rujuk\nPustu/\nPuskesmas");
        $sheet->getStyle("AC{$row16}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        
        // ==================== KOLOM PENIMBANGAN & PEMERIKSAAN (B–N) ====================
        $sheet->mergeCells("B{$row18}:B{$row20}");
        $sheet->setCellValue("B{$row18}", "Berat\nBadan\n(Kg)");

        $sheet->mergeCells("C{$row18}:C{$row20}");
        $sheet->setCellValue("C{$row18}", "Tinggi\nBadan\n(Cm)");

        $sheet->mergeCells("D{$row18}:D{$row20}");
        $sheet->setCellValue("D{$row18}", "IMT\nSangat Kurus (SK)/\nKurus (K)/\nNormal (N)/\nGemuk (G)/\nObesitas (O)");

        $sheet->mergeCells("E{$row18}:E{$row20}");
        $sheet->setCellValue("E{$row18}", "Lingkar\nPerut\n(Cm)");

        $sheet->mergeCells("F{$row18}:F{$row20}");
        $sheet->setCellValue("F{$row18}", "Lingkar\nLengan\nAtas\n(Cm)");

        $sheet->mergeCells("G{$row18}:H{$row18}");
        $sheet->setCellValue("G{$row18}", 'Tekanan Darah');

        $sheet->mergeCells("G{$row19}:G{$row20}");
        $sheet->setCellValue("G{$row19}", "Sistole/\nDiastole");

        $sheet->mergeCells("H{$row19}:H{$row20}");
        $sheet->setCellValue("H{$row19}", "Hasil\n(Rendah/\nNormal/\nTinggi)");

        $sheet->mergeCells("I{$row18}:J{$row18}");
        $sheet->setCellValue("I{$row18}", 'Gula Darah');

        $sheet->mergeCells("I{$row19}:I{$row20}");
        $sheet->setCellValue("I{$row19}", "Kadar\nGula Darah\nSewaktu\nmg/dL");

        $sheet->mergeCells("J{$row19}:J{$row20}");
        $sheet->setCellValue("J{$row19}", "Hasil\n(Rendah/\nNormal/\nTinggi)");

        $sheet->mergeCells("K{$row18}:L{$row18}");
        $sheet->setCellValue("K{$row18}", 'Tes Hitung Jari Tangan');
        $sheet->setCellValue("K{$row19}", 'Mata Kanan');
        $sheet->setCellValue("L{$row19}", 'Mata Kiri');
        $sheet->setCellValue("K{$row20}", "Normal/\nGangguan");
        $sheet->setCellValue("L{$row20}", "Normal/\nGangguan");

        $sheet->mergeCells("M{$row18}:N{$row18}");
        $sheet->setCellValue("M{$row18}", 'Tes Berbisik');
        $sheet->setCellValue("M{$row19}", "Telinga\nKanan");
        $sheet->setCellValue("N{$row19}", "Telinga\nKiri");
        $sheet->setCellValue("M{$row20}", "Normal/\nGangguan");
        $sheet->setCellValue("N{$row20}", "Normal/\nGangguan");

        // ==================== KUESIONER PUMA (O–V) ====================
        $sheet->setCellValue("O{$row18}", "Jenis\nKelamin");
        $sheet->setCellValue("P{$row18}", "Usia");
        $sheet->setCellValue("Q{$row18}", "Merokok");

        $sheet->mergeCells("R{$row18}:R{$row20}");
        $sheet->setCellValue("R{$row18}", "Apakah Anda sering merasa\nnapas pendek saat berjalan\ncepat di jalan datar atau\nsedikit menanjak?\n\n(Tidak = 0 | Ya = 5)");

        $sheet->mergeCells("S{$row18}:S{$row20}");
        $sheet->setCellValue("S{$row18}", "Apakah Anda sering\nmempunyai dahak dari paru\natau sulit mengeluarkan\ndahak saat tidak flu?\n\n(Tidak = 0 | Ya = 4)");

        $sheet->mergeCells("T{$row18}:T{$row20}");
        $sheet->setCellValue("T{$row18}", "Apakah Anda biasanya\nbatuk saat tidak sedang\nmenderita flu?\n\n(Tidak = 0 | Ya = 4)");

        $sheet->mergeCells("U{$row18}:U{$row20}");
        $sheet->setCellValue("U{$row18}", "Pernahkah dokter/tenaga\nkesehatan meminta Anda\nmeniup alat spirometri\natau peakflow meter?\n\n(Tidak = 0 | Ya = 5)");

        $sheet->mergeCells("V{$row18}:V{$row20}");
        $sheet->setCellValue("V{$row18}", "Skor\nPUMA");

        $sheet->mergeCells("O{$row19}:O{$row20}");
        $sheet->setCellValue("O{$row19}", "Pr = 0\nLk = 1");

        $sheet->mergeCells("P{$row19}:P{$row20}");
        $sheet->setCellValue("P{$row19}", "40-49 = 0\n50-59 = 1\n≥ 60 = 2");

        $sheet->mergeCells("Q{$row19}:Q{$row20}");
        $sheet->setCellValue("Q{$row19}", "Tidak = 0\n<20 Bks/Th = 0\n20-39 Bks/Th = 1\n≥40 Bks/Th = 2");

        $sheet->setCellValue("R{$row20}", "Tidak = 0\nYa = 5");
        $sheet->setCellValue("S{$row20}", "Tidak = 0\nYa = 4");
        $sheet->setCellValue("T{$row20}", "Tidak = 0\nYa = 4");
        $sheet->setCellValue("U{$row20}", "Tidak = 0\nYa = 5");

        $sheet->mergeCells("V{$row19}:V{$row20}");
        $sheet->setCellValue("V{$row19}", "< 6\n≥ 6");

        // ==================== SKRINING TBC (W–Z) ====================
        $sheet->mergeCells("W{$row18}:Z{$row18}");
        $sheet->setCellValue("W{$row18}", 'Skrining Gejala TBC (jika 2 gejala terpenuhi maka dirujuk ke Puskesmas)');
        $sheet->getStyle("W{$row18}:Z{$row18}")->getFont()->setBold(true);
        $sheet->getStyle("W{$row18}:Z{$row18}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells("W{$row19}:W{$row20}");
        $sheet->setCellValue("W{$row19}", "Batuk\nterus\nmenerus\n(Ya/Tidak)");

        $sheet->mergeCells("X{$row19}:X{$row20}");
        $sheet->setCellValue("X{$row19}", "Demam\nlebih dari\n2 minggu\n(Ya/Tidak)");

        $sheet->mergeCells("Y{$row19}:Y{$row20}");
        $sheet->setCellValue("Y{$row19}", "BB tidak\nnaik atau\nturun dalam\n2 bulan\n(Ya/Tidak)");

        $sheet->mergeCells("Z{$row19}:Z{$row20}");
        $sheet->setCellValue("Z{$row19}", "Kontak erat\ndengan\nPasien TBC\n(Ya/Tidak)");
        

        // ==================== ISI DATA RIWAYAT (mulai baris 21) ====================
        $row21 = $r(21); //Baris 21
        // =====================================================================
        // BARIS 21 → NOMOR KOLOM BACKGROUND WARNA ABU2 (BFBFBF)
        // =====================================================================
        $iterator = $sheet->getColumnIterator('A', 'AC');

        $noAks = 1;
        foreach ($iterator as $column) {
            $col = $column->getColumnIndex(); // A … Z
            $sheet->setCellValue("{$col}{$row21}", $noAks++);
        }

        $sheet->getStyle("A{$row21}:AC{$row21}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->getRowDimension(20)->setRowHeight(20);
        $sheet->getStyle("A{$row21}:AC{$row21}")->getFont()->setBold(true);

        $sheet->getStyle("A{$row21}:AC{$row21}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB("FFBFBFBF");

        // =====================================================================

        $row22 = $r(22);

        foreach ($periksas as $periksa) {
            $sheet->setCellValue("A{$row22}", $periksa->tanggal_periksa ?? '-');

            $imt = ($periksa->tinggi_badan > 0)
                ? round($periksa->berat_badan / (($periksa->tinggi_badan / 100) ** 2), 2)
                : 0;

            $kategori = $imt < 17   ? 'SK'
                      : ($imt < 18.5 ? 'K'
                      : ($imt < 25   ? 'N'
                      : ($imt < 30   ? 'G' : 'O')));

            $sheet->setCellValue("B{$row22}", $periksa->berat_badan ?? '');
            $sheet->setCellValue("C{$row22}", $periksa->tinggi_badan ?? '');
            $sheet->setCellValue("D{$row22}", $kategori);
            $sheet->setCellValue("E{$row22}", $periksa->lingkar_perut ?? '');
            $sheet->setCellValue("F{$row22}", $periksa->lingkar_lengan_atas ?? '');
            $sheet->setCellValue("G{$row22}", ($periksa->sistole ?? '').'/'.($periksa->diastole ?? ''));
            $sheet->setCellValue(
                "H{$row22}",
                ($periksa->sistole >= 140 || $periksa->diastole >= 90) ? 'Tinggi' : 'Normal'
            );
            $sheet->setCellValue("I{$row22}", $periksa->gula_darah ?? '');
            $sheet->setCellValue(
                "J{$row22}",
                $periksa->gula_darah > 200 ? 'Tinggi'
                    : ($periksa->gula_darah < 70 ? 'Rendah' : 'Normal')
            );
            $sheet->setCellValue("K{$row22}", $periksa->mata_kanan ?? 'Normal');
            $sheet->setCellValue("L{$row22}", $periksa->mata_kiri ?? 'Normal');
            $sheet->setCellValue("M{$row22}", $periksa->telinga_kanan ?? 'Normal');
            $sheet->setCellValue("N{$row22}", $periksa->telinga_kiri ?? 'Normal');

            $jkSkor   = ($warga->jenis_kelamin === 'Laki-laki' || $warga->jenis_kelamin === 'L') ? 1 : 0;
            $umur     = $warga->tanggal_lahir ? helper_umur($warga->tanggal_lahir) : 0;
            $usiaSkor = $umur >= 60 ? 2 : ($umur >= 50 ? 1 : 0);

            $merokokSkor = match ($periksa->merokok ?? 0) {
                0 => 0,
                1 => 0,
                2 => 1,
                3 => 2,
                default => 0,
            };

            $q1 = ($periksa->puma_napas_pendek ?? 'Tidak') === 'Ya' ? 5 : 0;
            $q2 = ($periksa->puma_dahak ?? 'Tidak')        === 'Ya' ? 4 : 0;
            $q3 = ($periksa->puma_batuk ?? 'Tidak')        === 'Ya' ? 4 : 0;
            $q4 = ($periksa->puma_tes_paru ?? 'Tidak')     === 'Ya' ? 5 : 0;

            $totalPuma = $jkSkor + $usiaSkor + $merokokSkor + $q1 + $q2 + $q3 + $q4;

            $sheet->setCellValue("O{$row22}", $jkSkor);
            $sheet->setCellValue("P{$row22}", $usiaSkor);
            $sheet->setCellValue("Q{$row22}", $merokokSkor);
            $sheet->setCellValue("R{$row22}", $q1 ? 'Ya' : 'Tidak');
            $sheet->setCellValue("S{$row22}", $q2 ? 'Ya' : 'Tidak');
            $sheet->setCellValue("T{$row22}", $q3 ? 'Ya' : 'Tidak');
            $sheet->setCellValue("U{$row22}", $q4 ? 'Ya' : 'Tidak');
            $sheet->setCellValue("V{$row22}", $totalPuma >= 6 ? '≥ 6' : $totalPuma);

            $sheet->setCellValue("W{$row22}", $periksa->tbc_batuk       ?? 'Tidak');
            $sheet->setCellValue("X{$row22}", $periksa->tbc_demam       ?? 'Tidak');
            $sheet->setCellValue("Y{$row22}", $periksa->tbc_bb_turun    ?? 'Tidak');
            $sheet->setCellValue("Z{$row22}", $periksa->tbc_kontak_erat ?? 'Tidak');
            $sheet->setCellValue("AA{$row22}", $periksa->kontrasepsi ?? '-');
            $sheet->setCellValue("AB{$row22}", $periksa->edukasi     ?? '-');

            $rujuk = [];
            if (($periksa->sistole >= 140 || $periksa->diastole >= 90) || ($periksa->gula_darah > 200)) {
                $rujuk[] = 'TD/Gula Darah Tinggi';
            }
            if ($totalPuma > 6) {
                $rujuk[] = 'Skor PUMA >6';
            }

            $gejalaTBC = collect([
                $periksa->tbc_batuk,
                $periksa->tbc_demam,
                $periksa->tbc_bb_turun,
                $periksa->tbc_kontak_erat,
            ])->filter(fn($v) => $v === 'Ya')->count();

            if ($gejalaTBC >= 2) {
                $rujuk[] = 'Suspek TBC';
            }

            if (!empty($rujuk)) {
                $sheet->setCellValue("AC{$row22}", 'YA (' . implode(', ', $rujuk) . ')');
                $sheet->getStyle("AC{$row22}")
                    ->getFont()->setBold(true)->getColor()->setARGB('FFFF0000');
            } else {
                $sheet->setCellValue(
                    "AC{$row22}",
                    ($periksa->rujuk_puskesmas == 1 ? 'Ya' : 'Tidak')
                );
            }

            $row22++;
        }

        $lastRow = $row22 - 1;

        // ==================== WARNA BACKGROUND & BORDER KARTU INI ====================
        // ==================== WARNA BACKGROUND ====================
        $fill = [
            "A{$row18}:A{$row20}"   => "FFFCE2D2",
            "B{$row18}:B{$row20}"   => "FFFFE79B",
            "D{$row18}:D{$row20}"   => "FFFFFFCC",
            "E{$row18}:E{$row20}"   => "FFFFE79B",
            "G{$row18}:G{$row20}"   => "FFFFE79B",
            "H{$row18}:H{$row20}"   => "FFFFE79B",

            "I{$row18}:AA{$row20}"  => "FFD7E1F3",
            "AA{$row16}:AA{$row20}" => "FFD7E1F3",
            "AC{$row16}:AC{$row20}" => "FFD7E1F3",
            "I{$row18}:N{$row20}"   => "FFD7E1F3",
            "O{$row17}:V{$row20}"   => "FFD7E1F3",
            "W{$row17}:Z{$row20}"   => "FFD7E1F3",

            "AB{$row16}:AB{$row20}" => "FFCCCCFF",
            "A{$row17}:N{$row17}"   => "FFD8D8D8",
            "A{$row16}:Z{$row16}"   => "FFD8D8D8",

            "F{$row17}:F{$row20}"   => "FFFFE79B",
            "C{$row17}:C{$row20}"   => "FFFFE79B",
        ];

        foreach ($fill as $range => $color) {
            $sheet->getStyle($range)
                ->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB($color);
        }

        // ==================== STYLING UMUM ====================
        $sheet->getStyle("A{$row18}:AC{$row20}")
            ->getFont()->setBold(true);

        $sheet->getStyle("A{$row16}:AC{$row21}")
            ->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THICK)
            ->getColor()->setRGB('FFFFFF');

        $sheet->getStyle("A{$row21}:AC{$lastRow}")
            ->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)   // lebih tebal dari medium
            ->getColor()->setRGB('000000');          // hitam


        $sheet->getStyle("A{$row18}:AC{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        
        // autosize kolom (boleh cukup sekali di luar loop, tapi aman juga di sini)
        foreach (array_merge(range('A', 'Z'), ['AA', 'AB', 'AC']) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(false);
        }

        // row height hanya untuk baris header (per kartu)
        $sheet->getRowDimension($row17)->setRowHeight(45);
        $sheet->getRowDimension($row18)->setRowHeight(75);
        $sheet->getRowDimension($row19)->setRowHeight(50);
        $sheet->getRowDimension($row20)->setRowHeight(60);


        return $lastRow;
    }
}
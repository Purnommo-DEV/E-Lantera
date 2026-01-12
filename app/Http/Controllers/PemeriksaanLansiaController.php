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
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class PemeriksaanLansiaController extends Controller
{

    public function index()
    {
        return view('page.lansia.index');
    }

    public function data(Request $request)
    {
        $query = Warga::with('pemeriksaanLansiaTerakhir')
            ->whereRaw('TIMESTAMPDIFF(YEAR, tanggal_lahir, CURDATE()) >= 60')
            ->select('id', 'nik', 'nama', 'tanggal_lahir');

        // Filter "Sudah Periksa Hari Ini"
        if ($request->query('filter') === 'hari_ini') {
            $query->whereHas('pemeriksaanLansiaTerakhir', function ($q) {
                $q->whereDate('tanggal_periksa', today());
            });
        }

        $lansia = $query->get();

        $data = $lansia->map(function ($w) {
            // Hitung umur (tahun & bulan) — tetap sama
            $lahir = $w->tanggal_lahir ? Carbon::parse($w->tanggal_lahir) : null;
            if ($lahir) {
                $diff = $lahir->diff(now());
                $tahun = $diff->y;
                $bulan = $diff->m;
                $umur = $tahun > 0 ? "{$tahun} thn {$bulan} bln" : "{$bulan} bln";
            } else {
                $umur = '-';
            }

            $p = $w->pemeriksaanLansiaTerakhir;

            $skilasPositif = 0;
            if ($p) {
                $skilasPositif = collect($p->getAttributes())
                    ->filter(function ($value, $key) {
                        return str_starts_with($key, 'skil_')
                            && !in_array($key, ['skil_rujuk_otomatis', 'skil_rujuk_manual', 'skil_edukasi', 'skil_catatan'])
                            && $value == 1;
                    })
                    ->count();
            }

            return [
                'id' => $w->id,
                'nik' => $w->nik,
                'nama' => $w->nama,
                'umur' => $umur,
                'terakhir' => $p
                    ? Carbon::parse($p->tanggal_periksa)->format('d-m-Y')
                    : '<span class="text-red-600 font-bold">Belum pernah diperiksa</span>',
                'aks_total_skor' => $p?->aks_total_skor ?? '-',
                'aks_kategori' => $p?->aks_kategori ?? '-',
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
            'data' => $data,
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
        // ==================== AMBIL DATA DASAR (WHITELIST) ====================
        $data = $request->all();

        // ==================== AKS ====================
        $aksFields = [
            'bab_s0_tidak_terkendali', 'bab_s1_kadang_tak_terkendali', 'bab_s2_terkendali',
            'bak_s0_tidak_terkendali_kateter', 'bak_s1_kadang_1x24jam', 'bak_s2_mandiri',
            'diri_s0_butuh_orang_lain', 'diri_s1_mandiri',
            'wc_s0_tergantung_lain', 'wc_s1_perlu_beberapa_bisa_sendiri', 'wc_s2_mandiri',
            'makan_s0_tidak_mampu', 'makan_s1_perlu_pemotongan', 'makan_s2_mandiri',
            'bergerak_s0_tidak_mampu', 'bergerak_s1_butuh_2orang',
            'bergerak_s2_butuh_1orang', 'bergerak_s3_mandiri',
            'jalan_s0_tidak_mampu', 'jalan_s1_kursi_roda',
            'jalan_s2_bantuan_1orang', 'jalan_s3_mandiri',
            'pakaian_s0_tergantung_lain', 'pakaian_s1_sebagian_dibantu', 'pakaian_s2_mandiri',
            'tangga_s0_tidak_mampu', 'tangga_s1_butuh_bantuan', 'tangga_s2_mandiri',
            'mandi_s0_tergantung_lain', 'mandi_s1_mandiri',
        ];

        foreach ($aksFields as $field) {
            $data["aks_{$field}"] = $request->has("aks_{$field}") ? 1 : 0;
        }

        $aksTotal = 0;

        $aksScoreMap = [
            's0' => 0,
            's1' => 1,
            's2' => 2,
            's3' => 3,
        ];

        foreach ($aksFields as $field) {
            if ($request->has("aks_{$field}")) {
                // ambil suffix s0/s1/s2/s3 dari nama field
                if (preg_match('/_s([0-3])_/', $field, $m)) {
                    $aksTotal += $aksScoreMap['s' . $m[1]];
                }
            }
        }
        
        $data['aks_total_skor'] = $aksTotal;

        $aks = $data['aks_total_skor'];
        $data['aks_kategori'] = match (true) {
            $aks === 20              => 'M', // Mandiri
            $aks >= 12 && $aks <= 19 => 'R', // Risiko Ringan
            $aks >= 9  && $aks <= 11 => 'S', // Sedang
            $aks >= 5  && $aks <= 8  => 'B', // Berat
            default                  => 'T', // 0–4 Total
        };
        
        $data['aks_rujuk_otomatis'] = $aks < 20;
        
        // ==================== SKIL ====================
        $skilFields = [
            'orientasi_waktu_tempat', 'mengulang_ketiga_kata', 'tes_berdiri_dari_kursi',
            'bb_berkurang_3kg_dalam_3bulan', 'hilang_nafsu_makan', 'lla_kurang_21cm',
            'masalah_pada_mata', 'tes_melihat', 'perasaan_sedih_tertekan',
            'sedikit_minat_atau_kenikmatan', 'imunisasi_covid'
        ];

        foreach ($skilFields as $f) {
            $data["skil_{$f}"] = $request->has("skil_{$f}") ? 1 : 0;
        }

        // ==================== KHUSUS ====================
        $data['skil_tes_bisik'] = $request->has('skil_tes_bisik') ? 1 : 0;
        $data['skil_tidak_dapat_dilakukan'] = $request->has('skil_tidak_dapat_dilakukan') ? 1 : 0;

        // ==================== HITUNG RUJUK OTOMATIS ====================
        $skilRujuk = false;

        // cek semua skil utama
        foreach ($skilFields as $f) {
            if ($data["skil_{$f}"] === 1) {
                $skilRujuk = true;
                break;
            }
        }

        // opsional: ikutkan field khusus
        if (
            $data['skil_tes_bisik'] === 1 ||
            $data['skil_tidak_dapat_dilakukan'] === 1
        ) {
            $skilRujuk = true;
        }

        $data['skil_rujuk_otomatis'] = $skilRujuk;

        // RUJUK MANUAL
        // AKS
        $data['aks_rujuk_manual'] = (int) $request->input('aks_rujuk_manual', 0);

        // SKILAS
        $data['skil_rujuk_manual'] = (int) $request->input('skil_rujuk_manual', 0);

        // ==================== CREATE vs UPDATE ====================
        if ($lansia && $lansia->exists) {
            // 🔥 UPDATE PASTI KE RECORD INI
            $lansia->update($data);
            return $lansia;
        }

        // 🔥 CREATE (PASTI BARU)
        $data['warga_id'] = $request->warga_id;

        return PemeriksaanLansia::create($data);
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

        // =====================================================================
        // 1. HEADER UTAMA & IDENTITAS AKS
        // =====================================================================
        $sheet->setCellValue('A1', 'Depan');
        $sheet->mergeCells('A1:AH1');

        // LOGO
        $logo = new Drawing();
        $logo->setName('Logo');
        $logo->setDescription('Logo Posyandu');
        $logo->setPath(public_path('posyandu.png'));

        $logo->setHeight(60);              // 🔥 JANGAN kegedean
        $logo->setResizeProportional(true);

        $logo->setCoordinates("K2");
        $logo->setOffsetX(3);
        $logo->setOffsetY(2);             // 🔥 sejajar teks

        $logo->setWorksheet($sheet);

        // penting: kolom jangan lebar
        $sheet->getColumnDimension('K')->setWidth(6);

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

        // LOGO
        $logo = new Drawing();
        $logo->setName('Logo');
        $logo->setDescription('Logo Posyandu');
        $logo->setPath(public_path('posyandu.png'));

        $logo->setHeight(60);              // 🔥 JANGAN kegedean
        $logo->setResizeProportional(true);

        $logo->setCoordinates("AM2");
        $logo->setOffsetX(3);
        $logo->setOffsetY(2);             // 🔥 sejajar teks

        $logo->setWorksheet($sheet);

        // penting: kolom jangan lebar
        $sheet->getColumnDimension('K')->setWidth(6);

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
        $sheet->getStyle("A16:AY22")
            ->getFont()->setBold(true);

        $sheet->getStyle("A16:AH22")
            ->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THICK)
            ->getColor()->setRGB('FFFFFF');

        $sheet->getStyle("AI16:AY22")
            ->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THICK)
            ->getColor()->setRGB('FFFFFF');

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

    public function exportSelected(Request $request)
    {
        $ids = $request->query('ids', '');
        if (empty($ids)) {
            return back()->with('error', 'Tidak ada data yang dipilih');
        }

        $idsArray = array_filter(explode(',', $ids), 'is_numeric');

        $wargas = Warga::with('pemeriksaanLansiaAll')
            ->whereIn('id', $idsArray)
            ->get();

        if ($wargas->isEmpty()) {
            return back()->with('error', 'Data lansia tidak ditemukan');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Kartu Lansia Terpilih');

        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        $this->setColumnWidths($sheet); // panggil fungsi yang sudah ada

        $offset = 0;
        $jarakAntarKartu = 10;

        foreach ($wargas as $warga) {
            $lastRow = $this->buildKartuLansiaOffset($sheet, $warga, $offset);
            $offset = $lastRow + $jarakAntarKartu;
        }

        $filename = "Kartu_Lansia_AKS_SKILAS_Terpilih_" . now()->format('Ymd_His') . ".xlsx";

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
    
    public function exportLansiaExcelSemua()
    {
        $wargas = Warga::with('pemeriksaanLansiaAll')
            ->whereHas('pemeriksaanLansiaAll')
            ->get();

        if ($wargas->isEmpty()) {
            abort(404, 'Belum ada data pemeriksaan lansia');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Kartu Lansia AKS-SKILAS');

        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        $this->setColumnWidths($sheet);

        $offset = 0;
        $jarakAntarKartu = 10;

        foreach ($wargas as $warga) {
            $lastRow = $this->buildKartuLansiaOffset($sheet, $warga, $offset);
            $offset = $lastRow + $jarakAntarKartu;
        }

        $filename = "Kartu_Lansia_AKS_SKILAS_Semua_" . now()->format('Ymd_His') . ".xlsx";

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function setColumnWidths($sheet)
    {
        $widths = [
            'A' => 20, 'B' => 10, 'C' => 10, 'D' => 10, 'E' => 9, 'F' => 9, 'G' => 9, 'H' => 9, 'I' => 9,
            'J' => 9, 'K' => 9, 'L' => 9, 'M' => 9, 'N' => 9, 'O' => 9, 'P' => 9, 'Q' => 9, 'R' => 9,
            'S' => 9, 'T' => 9, 'U' => 9, 'V' => 9, 'W' => 9, 'X' => 9, 'Y' => 9, 'Z' => 9,
            'AA' => 9, 'AB' => 9, 'AC' => 9, 'AD' => 9, 'AE' => 9, 'AF' => 14, 'AG' => 10, 'AH' => 10, 'AI' => 2,
            'AJ' => 20, 'AK' => 12, 'AL' => 12, 'AM' => 12, 'AN' => 12, 'AO' => 12, 'AP' => 12,
            'AQ' => 12, 'AR' => 12, 'AS' => 12, 'AT' => 12, 'AU' => 12, 'AV' => 12, 'AW' => 12,
            'AX' => 15, 'AY' => 12,
        ];
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
    }

    protected function buildKartuLansiaOffset($sheet, Warga $warga, int $offsetRow = 0): int
    {
        $r = fn($n) => $n + $offsetRow;

        $periksas = $warga->pemeriksaanLansiaAll;
        if ($periksas->isEmpty()) {
            $sheet->setCellValue('A' . $r(2), 'Belum ada data pemeriksaan lansia');
            return $r(5);
        }

        $fontSizeDefaultData = 10;
        $fontSizeHeaderUtama = 16;
        $fontSizeHeaderBlok = 14;
        $fontSizeHeaderKecil = 11;
        $fontSizeProfil = 11;
        $fontSizeDepanBelakang = 9;

        $headerTopRow = $r(18);
        $optionRowStart = $r(20);
        $optionRowEnd = $r(21);
        $aksFirstDataRow = $r(23);

        // ====================== HEADER DEPAN ======================
        $sheet->setCellValue('A' . $r(1), 'Depan');
        $sheet->mergeCells('A' . $r(1) . ':AH' . $r(1));

        // LOGO
        $logo = new Drawing();
        $logo->setName('Logo');
        $logo->setDescription('Logo Posyandu');
        $logo->setPath(public_path('posyandu.png'));

        $logo->setHeight(60);              // 🔥 JANGAN kegedean
        $logo->setResizeProportional(true);

        $logo->setCoordinates("K" . $r(2));
        $logo->setOffsetX(3);
        $logo->setOffsetY(2);             // 🔥 sejajar teks

        $logo->setWorksheet($sheet);

        // penting: kolom jangan lebar
        $sheet->getColumnDimension('K')->setWidth(6);

        // FONT
        $sheet->getStyle('A' . $r(1) . ':AH' . $r(1))->getFont()
            ->setSize($fontSizeDepanBelakang)
            ->setBold(true)
            ->getColor()
            ->setARGB('FFFFFFFF');
        
                // LOGO
        $logo = new Drawing();
        $logo->setName('Logo');
        $logo->setDescription('Logo Posyandu');
        $logo->setPath(public_path('posyandu.png'));

        $logo->setHeight(60);              // 🔥 JANGAN kegedean
        $logo->setResizeProportional(true);

        $logo->setCoordinates('AM'.$r(2));
        $logo->setOffsetX(3);
        $logo->setOffsetY(2);             // 🔥 sejajar teks

        $logo->setWorksheet($sheet);

        // penting: kolom jangan lebar
        $sheet->getColumnDimension('K')->setWidth(6);

        // ALIGNMENT
        $sheet->getStyle('A' . $r(1) . ':AH' . $r(1))->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        // BACKGROUND     
        $sheet->getStyle('A' . $r(1) . ':AH' . $r(1))->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FF00B050');

        $sheet->setCellValue('A' . $r(2), 'KARTU BANTU PEMERIKSAAN LANSIA (≥60 Tahun)');
        $sheet->mergeCells('A' . $r(2) . ':AH' . $r(2));
        $sheet->getStyle('A' . $r(2) . ':AH' . $r(2))->getFont()->setSize($fontSizeHeaderUtama)->setBold(true);
        $sheet->getStyle('A' . $r(2) . ':AH' . $r(2))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A' . $r(3), 'POSYANDU TAMAN CIPULIR ESTATE');
        $sheet->mergeCells('A' . $r(3) . ':AH' . $r(3));
        $sheet->getStyle('A' . $r(3) . ':AH' . $r(3))->getFont()->setSize($fontSizeHeaderUtama)->setBold(true);
        $sheet->getStyle('A' . $r(3) . ':AH' . $r(3))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // ====================== IDENTITAS DEPAN ======================
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
            $row = preg_replace('/\D/', '', $cell);

            $row = $r($row);
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

        foreach (range(5, 14) as $i) $sheet->setCellValue('C' . $r($i), ':');

        $dataIdentitas = [
            5 => $warga->nama_lengkap ?? $warga->nama,
            6 => $warga->nik,
            7 => $warga->tanggal_lahir ? Carbon::parse($warga->tanggal_lahir)->translatedFormat('d F Y') : '-',
            8 => $warga->alamat,
            9 => $warga->no_hp,
            10 => $warga->status_nikah,
            11 => $warga->pekerjaan,
            12 => sprintf('%s/%s/%s', $warga->dusun ?? '-', $warga->rt ?? '-', $warga->rw ?? '-'),
            13 => $warga->kecamatan,
            14 => $warga->desa,
        ];
        foreach ($dataIdentitas as $rowNum => $value) {
            $row = $r($rowNum);
            $sheet->mergeCells("D{$row}:F{$row}");
            $sheet->setCellValue("D{$row}", $value ?? '-');
            $sheet->getStyle("D{$row}:F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        }

        // ==============================
        // Jenis kelamin (dipindah ke G5)
        // ==============================
        $jenisRaw = trim($warga->jenis_kelamin ?? '');
        $rt = new RichText();
        $rt->createTextRun('( ');
        $tl = $rt->createTextRun('Laki-laki');
        if (strcasecmp($jenisRaw, 'Laki-laki') === 0 || $jenisRaw === 'L') $tl->getFont()->getColor()->setARGB(Color::COLOR_RED);
        $rt->createTextRun(' / ');
        $tp = $rt->createTextRun('Perempuan');
        if (strcasecmp($jenisRaw, 'Perempuan') === 0 || $jenisRaw === 'P') $tp->getFont()->getColor()->setARGB(Color::COLOR_RED);
        $rt->createTextRun(' )');
        $sheet->setCellValue('G' . $r(5), $rt);

        // ==============================
        // Umur (dipindah ke G6)
        // ==============================
        $tahun = $warga->tanggal_lahir ? Carbon::parse($warga->tanggal_lahir)->diff(now())->y : 0;
        $sheet->setCellValue('G' . $r(6), "( {$tahun} Tahun )");

        // ====================== RIWAYAT & PERILAKU ======================
        $sheet->mergeCells('Q' . $r(5) . ':R' . $r(6));
        $sheet->setCellValue('Q' . $r(5), "Riwayat Keluarga\n(lingkari jika ada)");
        $sheet->getStyle('Q' . $r(5) . ':R' . $r(6))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);

        $riwayatCols = [['S','T'],['U','V'],['W','X'],['Y','Z'],['AA','AB'],['AC','AD']];
        $items = ['a. Hipertensi','b. DM','c. Stroke','d. Jantung','f. Kanker','g. Kolesterol Tinggi'];
        foreach ($riwayatCols as $i => [$c1, $c2]) {
            if (!isset($items[$i])) break;
            $sheet->mergeCells("{$c1}" . $r(5) . ":{$c2}" . $r(5));
            $sheet->setCellValue("{$c1}" . $r(5), $items[$i]);
        }
        $sheet->mergeCells('Q' . $r(7) . ':R' . $r(8));
        $sheet->setCellValue('Q' . $r(7), "Riwayat Diri Sendiri\n(lingkari jika ada)");
        foreach ($riwayatCols as $i => [$c1, $c2]) {
            if (!isset($items[$i])) break;
            $sheet->mergeCells("{$c1}" . $r(7) . ":{$c2}" . $r(7));
            $sheet->setCellValue("{$c1}" . $r(7), $items[$i]);
        }

        $sheet->mergeCells('Q' . $r(9) . ':R' . $r(12));
        $sheet->setCellValue('Q' . $r(9), "Perilaku Berisiko Diri Sendiri\n(lingkari jika ada)");
        foreach ([9,10,11,12] as $n) $sheet->mergeCells('S' . $r($n) . ':V' . $r($n));
        $sheet->setCellValue('S' . $r(9), "a. Merokok");
        $sheet->setCellValue('S' . $r(10), "b. Konsumsi Tinggi Gula");
        $sheet->setCellValue('S' . $r(11), "c. Konsumsi Tinggi Garam");
        $sheet->setCellValue('S' . $r(12), "d. Konsumsi Tinggi Lemak");
        foreach ([9,10,11,12] as $n) {
            $sheet->mergeCells('Y' . $r($n) . ':Z' . $r($n));
            $sheet->setCellValue('Y' . $r($n), ': Ya/Tidak');
        }

        $sheet->getStyle('Q' . $r(5) . ':AD' . $r(12))->getFont()->setSize($fontSizeHeaderKecil);
        $sheet->getStyle('Q' . $r(5) . ':AD' . $r(12))->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);

        // Langkah warna AF
        foreach (range(5,9) as $i) {
            $row = $r($i);
            $sheet->setCellValue("AF{$row}", ': Disi langkah ' . ($i-4));
            $colors = ['FFFCE2D2','FFFFE79B','FFFFFFCC','FFD7E1F3','FFCCCCFF'];
            $sheet->getStyle("AF{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($colors[$i-5]);
        }

        $styleCenterWrap = function ($range) use ($sheet) {
            $sheet->getStyle($range)->getAlignment()
                ->setWrapText(true)
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
        };

        // ====================== HEADER AKS ======================
        $sheet->mergeCells("B" . $r(16).":AE" . $r(17));
        $rtAks = new RichText();
        $rtAks->createTextRun("Aktifitas Kehidupan Sehari-hari (AKS)")->getFont()->setBold(true)->setSize(14);
        $rtAks->createTextRun("\n(Jika hasil perhitungan skor <20 atau termasuk kelompok Ringan, Sedang, Berat dan Total maka dilakukan rujuk ke Pustu/Puskesmas)")->getFont()->setSize($fontSizeHeaderKecil);
        $sheet->setCellValue("B" . $r(16), $rtAks);
        $sheet->getStyle("B" . $r(16).":AE" . $r(17))->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("B" . $r(16).":AE" . $r(17))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $sheet->mergeCells("A" . $r(16).":A{$optionRowEnd}");
        $sheet->setCellValue("A" . $r(16), "Waktu ke\nPosyandu\n(tanggal/bulan/tahun)");
        $sheet->getStyle("A" . $r(16).":A{$optionRowEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle("A" . $r(16).":A{$optionRowEnd}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A" . $r(16).":A{$optionRowEnd}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFCE2D2');

        // BAB
        $sheet->mergeCells("B{$headerTopRow}:D" . $r(19));
        $sheet->setCellValue("B{$headerTopRow}", "Mengendalikan rangsang Buang Air Besar (BAB)");
        $styleCenterWrap("B{$headerTopRow}:D" . $r(19));

        $sheet->mergeCells("B{$optionRowStart}:B{$optionRowEnd}"); $sheet->setCellValue("B{$optionRowStart}", "Skor 0\nTidak terkendali / tak teratur\n(perlu pencahar)");
        $styleCenterWrap("B{$optionRowStart}:B{$optionRowEnd}");

        $sheet->mergeCells("C{$optionRowStart}:C{$optionRowEnd}"); $sheet->setCellValue("C{$optionRowStart}", "Skor 1\nKadang-kadang tak terkendali\n(≥1x/minggu)");
        $styleCenterWrap("C{$optionRowStart}:C{$optionRowEnd}");

        $sheet->mergeCells("D{$optionRowStart}:D{$optionRowEnd}"); $sheet->setCellValue("D{$optionRowStart}", "Skor 2\nTerkendali & mandiri");
        $styleCenterWrap("D{$optionRowStart}:D{$optionRowEnd}");

        // BAK
        $sheet->mergeCells("E{$headerTopRow}:G" . $r(19));
        $sheet->setCellValue("E{$headerTopRow}", "Mengendalikan rangsang Buang Air Kecil (BAK)");
        $styleCenterWrap("E{$headerTopRow}:G" . $r(19));

        $sheet->mergeCells("E{$optionRowStart}:E{$optionRowEnd}");
        $sheet->setCellValue("E{$optionRowStart}", "Skor 0\nTidak terkendali / pakai kateter");
        $styleCenterWrap("E{$optionRowStart}:E{$optionRowEnd}");

        $sheet->mergeCells("F{$optionRowStart}:F{$optionRowEnd}");
        $sheet->setCellValue("F{$optionRowStart}", "Skor 1\nKadang-kadang tak terkendali\n(≥1x/24 jam)");
        $styleCenterWrap("F{$optionRowStart}:F{$optionRowEnd}");

        $sheet->mergeCells("G{$optionRowStart}:G{$optionRowEnd}");
        $sheet->setCellValue("G{$optionRowStart}", "Skor 2\nTerkendali & mandiri");
        $styleCenterWrap("G{$optionRowStart}:G{$optionRowEnd}");

        // Membersihkan diri
        $sheet->mergeCells("H{$headerTopRow}:I" . $r(19));
        $sheet->setCellValue("H{$headerTopRow}", "Membersihkan diri\n(mencuci wajah, menyikat rambut, sikat gigi, dll)");
        $styleCenterWrap("H{$headerTopRow}:I" . $r(19));

        $sheet->mergeCells("H{$optionRowStart}:H{$optionRowEnd}");
        $sheet->setCellValue("H{$optionRowStart}", "Skor 0\nButuh bantuan orang lain");
        $styleCenterWrap("H{$optionRowStart}:H{$optionRowEnd}");

        $sheet->mergeCells("I{$optionRowStart}:I{$optionRowEnd}");
        $sheet->setCellValue("I{$optionRowStart}", "Skor 1\nMandiri");
        $styleCenterWrap("I{$optionRowStart}:I{$optionRowEnd}");


        // Ke WC
        $sheet->mergeCells("J{$headerTopRow}:L" . $r(19));
        $sheet->setCellValue("J{$headerTopRow}", "Penggunaan WC (keluar masuk WC, melepas/memakai celana, cebok, menyiram)");
        $styleCenterWrap("J{$headerTopRow}:L" . $r(19));

        foreach (['J'=>'Skor 0\nTergantung orang lain',
                  'K'=>'Skor 1\nPerlu pertolongan pada beberapa kegiatan',
                  'L'=>'Skor 2\nMandiri'] as $col => $text) {
            $sheet->mergeCells("{$col}{$optionRowStart}:{$col}{$optionRowEnd}");
            $sheet->setCellValue("{$col}{$optionRowStart}", $text);
            $styleCenterWrap("{$col}{$optionRowStart}:{$col}{$optionRowEnd}");
        }

        // Makan
        $sheet->mergeCells("M{$headerTopRow}:O" . $r(19));
        $sheet->setCellValue("M{$headerTopRow}", "Makan minum\n(jika makan harus berupa potongan, dianggap dibantu)");
        $styleCenterWrap("M{$headerTopRow}:O" . $r(19));

        foreach (['M'=>'Skor 0\nTidak mampu',
                  'N'=>'Skor 1\nPerlu bantuan (dipotong / disuapi)',
                  'O'=>'Skor 2\nMandiri'] as $col => $text) {
            $sheet->mergeCells("{$col}{$optionRowStart}:{$col}{$optionRowEnd}");
            $sheet->setCellValue("{$col}{$optionRowStart}", $text);
            $styleCenterWrap("{$col}{$optionRowStart}:{$col}{$optionRowEnd}");
        }

        // Bergerak
        $sheet->mergeCells("P{$headerTopRow}:S" . $r(19));
        $sheet->setCellValue("P{$headerTopRow}", "Bergerak dari kursi roda ke tempat tidur dan sebaliknya (termasuk duduk di tempat tidur)");
        $styleCenterWrap("P{$headerTopRow}:S" . $r(19));

        foreach ([
            'P'=>'Skor 0\nTidak mampu',
            'Q'=>'Skor 1\nButuh bantuan 2 orang',
            'R'=>'Skor 2\nButuh bantuan 1 orang',
            'S'=>'Skor 3\nMandiri'
        ] as $col => $text) {
            $sheet->mergeCells("{$col}{$optionRowStart}:{$col}{$optionRowEnd}");
            $sheet->setCellValue("{$col}{$optionRowStart}", $text);
            $styleCenterWrap("{$col}{$optionRowStart}:{$col}{$optionRowEnd}");
        }

        // Berjalan
        $sheet->mergeCells("T{$headerTopRow}:W" . $r(19));
        $sheet->setCellValue("T{$headerTopRow}", "Berjalan di tempat rata (atau jika tidak bisa berjalan, menjalankan kursi roda)");
        $styleCenterWrap("T{$headerTopRow}:W" . $r(19));

        foreach ([
            'T' => "Skor 0\nTidak mampu",
            'U' => "Skor 1\nHanya dengan kursi roda",
            'V' => "Skor 2\nBerjalan dengan bantuan 1 orang",
            'W' => "Skor 3\nMandiri (boleh pakai tongkat)",
        ] as $col => $text) {
            $sheet->mergeCells("{$col}{$optionRowStart}:{$col}{$optionRowEnd}");
            $sheet->setCellValue("{$col}{$optionRowStart}", $text);
            $styleCenterWrap("{$col}{$optionRowStart}:{$col}{$optionRowEnd}");
        }

        // Berpakaian
        $sheet->mergeCells("X{$headerTopRow}:Z" . $r(19));
        $sheet->setCellValue("X{$headerTopRow}", "Berpakaian (memakai baju, ikat sepatu, dsb)");
        $styleCenterWrap("X{$headerTopRow}:Z" . $r(19));

        foreach ([
            'X' => "Skor 0\nTergantung orang lain",
            'Y' => "Skor 1\nSebagian dibantu",
            'Z' => "Skor 2\nMandiri",
        ] as $col => $text) {
            $sheet->mergeCells("{$col}{$optionRowStart}:{$col}{$optionRowEnd}");
            $sheet->setCellValue("{$col}{$optionRowStart}", $text);
            $styleCenterWrap("{$col}{$optionRowStart}:{$col}{$optionRowEnd}");
        }

        // Naik tangga
        $sheet->mergeCells("AA{$headerTopRow}:AC" . $r(19));
        $sheet->setCellValue("AA{$headerTopRow}", "Naik turun 1 lantai tangga");
        $styleCenterWrap("AA{$headerTopRow}:AC" . $r(19));

        foreach ([
            'AA' => "Skor 0\nTidak mampu",
            'AB' => "Skor 1\nButuh bantuan + pegangan",
            'AC' => "Skor 2\nMandiri",
        ] as $col => $text) {
            $sheet->mergeCells("{$col}{$optionRowStart}:{$col}{$optionRowEnd}");
            $sheet->setCellValue("{$col}{$optionRowStart}", $text);
            $styleCenterWrap("{$col}{$optionRowStart}:{$col}{$optionRowEnd}");
        }

        // Mandi
        $sheet->mergeCells("AD{$headerTopRow}:AE" . $r(19));
        $sheet->setCellValue("AD{$headerTopRow}", "Mandi sendiri\n(masuk-keluar kamar mandi)");
        $styleCenterWrap("AD{$headerTopRow}:AE" . $r(19));

        foreach ([
            'AD' => "Skor 0\nTergantung orang lain",
            'AE' => "Skor 1\nMandiri",
        ] as $col => $text) {
            $sheet->mergeCells("{$col}{$optionRowStart}:{$col}{$optionRowEnd}");
            $sheet->setCellValue("{$col}{$optionRowStart}", $text);
            $styleCenterWrap("{$col}{$optionRowStart}:{$col}{$optionRowEnd}");
        }

        // Tingkat ketergantungan

        $sheet->mergeCells("AF".$r(16).":AF" . $r(19));
        $sheet->setCellValue("AF".$r(16), "Tingkat Ketergantungan\n(M/R/S/B/T)");
        $sheet->getStyle("AF".$r(16).":AF" . $r(19))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle("AF".$r(16).":AF" . $r(19))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->mergeCells("AF{$optionRowStart}:AF{$optionRowEnd}");
        $sheet->setCellValue(
            "AF{$optionRowStart}",
            "Mandiri (M=20)\nRingan (R=12-19)\nSedang (S=9-11)\nBerat (B=5-8)\nTotal (T=0-4)"
        );
        $styleCenterWrap("AF{$optionRowStart}:AF{$optionRowEnd}");


        // Edukasi & Rujuk AKS
        $sheet->mergeCells("AG".$r(16).":AG{$optionRowEnd}");
        $sheet->setCellValue("AG".$r(16), "Edukasi");
        $styleCenterWrap("AG".$r(16).":AG{$optionRowEnd}");
        $sheet->getStyle("AG".$r(16).":AG{$optionRowEnd}")
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $sheet->mergeCells("AH".$r(16).":AH{$optionRowEnd}");
        $sheet->setCellValue("AH".$r(16), "Rujuk\nPustu/Puskesmas");
        $styleCenterWrap("AH".$r(16).":AH{$optionRowEnd}");
        $sheet->getStyle("AH".$r(16).":AH{$optionRowEnd}")
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);


        // Warna header AKS
        $colorHeader = 'FFD7E1F3';
        $colorTanggal = 'FFFCE2D2';
        $colorEdukasi  = 'FFCCCCFF';

        $sheet->getStyle("A".$r(16).":A{$optionRowEnd}")
            ->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB($colorTanggal);
        $sheet->getStyle("AG".$r(16).":AG{$optionRowEnd}")
            ->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB($colorEdukasi);

        $headerRanges = [
            'B'.$r(16).':AE'.$r(17), 'B'.$headerTopRow.':D'.$optionRowEnd, 'E'.$headerTopRow.':G'.$optionRowEnd,
            'H'.$headerTopRow.':I'.$optionRowEnd, 'J'.$headerTopRow.':L'.$optionRowEnd, 'M'.$headerTopRow.':O'.$optionRowEnd,
            'P'.$headerTopRow.':S'.$optionRowEnd, 'T'.$headerTopRow.':W'.$optionRowEnd, 'X'.$headerTopRow.':Z'.$optionRowEnd,
            'AA'.$headerTopRow.':AC'.$optionRowEnd, 'AD'.$headerTopRow.':AE'.$optionRowEnd, 'AF'.$r(16).':AF'.$optionRowEnd,
            'AH'.$r(16).':AH'.$optionRowEnd,
        ];
        foreach ($headerRanges as $range) {
            $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($colorHeader);
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
        $sheet->setCellValue('AJ' . $r(1), 'Belakang');
        $sheet->mergeCells('AJ' . $r(1) . ':AY' . $r(1));
        
        // FONT
        $sheet->getStyle('AJ' . $r(1) . ':AY' . $r(1))->getFont()
            ->setSize($fontSizeDepanBelakang)
            ->setBold(true)->getColor()->setARGB('FFFFFFFF');

        // ALIGNMENT
        $sheet->getStyle('AJ' . $r(1) . ':AY' . $r(1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('AJ' . $r(1) . ':AY' . $r(1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF00B050');

        // BACKGROUND
        $sheet->setCellValue('AJ' . $r(2), 'KARTU BANTU PEMERIKSAAN LANSIA (≥60 Tahun)');
        $sheet->mergeCells('AJ' . $r(2) . ':AY' . $r(2));
        $sheet->getStyle('AJ' . $r(2) . ':AY' . $r(2))->getFont()->setSize($fontSizeHeaderUtama)->setBold(true);
        $sheet->getStyle('AJ' . $r(2) . ':AY' . $r(2))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('AJ' . $r(3), 'POSYANDU TAMAN CIPULIR ESTATE');
        $sheet->mergeCells('AJ' . $r(3) . ':AY' . $r(3));
        $sheet->getStyle('AJ' . $r(3) . ':AY' . $r(3))->getFont()->setSize($fontSizeHeaderUtama)->setBold(true);
        $sheet->getStyle('AJ' . $r(3) . ':AY' . $r(3))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Identitas SKILAS
        foreach ($labelsAKS as $cell => $text) {
            $row = preg_replace('/\D/', '', $cell);
            $row = $r($row);
            $sheet->mergeCells("AJ{$row}:AL{$row}");
            $sheet->setCellValue("AJ{$row}", $text);
            $sheet->getStyle("AJ{$row}:AL{$row}")->getFont()->setSize($fontSizeProfil)->setBold(true);
        }
        foreach (range(5,14) as $i) $sheet->setCellValue('AM' . $r($i), ':');
        foreach ($dataIdentitas as $rowNum => $value) {
            $row = $r($rowNum);
            $sheet->mergeCells("AN{$row}:AP{$row}");
            $sheet->setCellValue("AN{$row}", $value ?? '-');
        }
        $sheet->setCellValue('AQ' . $r(5), $rt);
        $sheet->setCellValue('AQ' . $r(6), "( {$tahun} Tahun )");

        // Header SKILAS
        $sheet->mergeCells("AJ".$r(16).":AJ{$optionRowEnd}");
        $sheet->setCellValue("AJ".$r(16), "Waktu ke\nPosyandu\n(tanggal/bulan/tahun)");
        $sheet->getStyle("AJ".$r(16).":AJ{$optionRowEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle("AJ".$r(16).":AJ{$optionRowEnd}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("AJ".$r(16).":AJ{$optionRowEnd}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFCE2D2');

        $sheet->mergeCells("AK".$r(16).":AV" . $r(17));
        $rtSkilas = new RichText();
        $rtSkilas->createTextRun("SKILAS - Skrining Risiko Lansia")->getFont()->setBold(true)->setSize(14);
        $rtSkilas->createTextRun("\nJika terdapat 1 atau lebih jawaban 'YA' → Wajib rujuk ke Puskesmas / RS")->getFont()->setSize($fontSizeHeaderKecil);
        $sheet->setCellValue("AK".$r(16), $rtSkilas);
        $sheet->getStyle("AK".$r(16).":AV" . $r(17))->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("AJ".$r(16).":AY" . $r(17))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Header kelompok SKILAS
        $sheet->mergeCells("AK{$headerTopRow}:AL" . $r(19));
        $sheet->setCellValue("AK{$headerTopRow}", "Penurunan Kognitif");
        $styleCenterWrap("AK{$headerTopRow}:AL" . $r(19));

        $sheet->mergeCells("AM{$headerTopRow}:AM" . $r(19));
        $sheet->setCellValue("AM{$headerTopRow}", "Keterbatasan Mobilisasi");
        $styleCenterWrap("AM{$headerTopRow}:AM" . $r(19));

        $sheet->mergeCells("AN{$headerTopRow}:AP" . $r(19));
        $sheet->setCellValue("AN{$headerTopRow}", "Malnutrisi");
        $styleCenterWrap("AN{$headerTopRow}:AP" . $r(19));

        $sheet->mergeCells("AQ{$headerTopRow}:AR" . $r(19));
        $sheet->setCellValue("AQ{$headerTopRow}", "Gangguan Penglihatan");
        $styleCenterWrap("AQ{$headerTopRow}:AR" . $r(19));

        $sheet->mergeCells("AS{$headerTopRow}:AT" . $r(19));
        $sheet->setCellValue("AS{$headerTopRow}", "Gangguan Pendengaran");
        $styleCenterWrap("AS{$headerTopRow}:AT" . $r(19));

        $sheet->mergeCells("AU{$headerTopRow}:AV" . $r(19));
        $sheet->setCellValue(
            "AU{$headerTopRow}",
            "Gejala Depresi\n(dalam 2 minggu terakhir)"
        );
        $styleCenterWrap("AU{$headerTopRow}:AV" . $r(19));

        // Kolom tunggal sampai optionRowEnd
        $sheet->mergeCells("AW{$headerTopRow}:AW{$optionRowEnd}");
        $sheet->setCellValue("AW{$headerTopRow}", "Imunisasi COVID");
        $styleCenterWrap("AW{$headerTopRow}:AW{$optionRowEnd}");

        $sheet->mergeCells("AX{$headerTopRow}:AX{$optionRowEnd}");
        $sheet->setCellValue("AX{$headerTopRow}", "Edukasi");
        $styleCenterWrap("AX{$headerTopRow}:AX{$optionRowEnd}");

        $sheet->mergeCells("AY{$headerTopRow}:AY{$optionRowEnd}");
        $sheet->setCellValue("AY{$headerTopRow}", "Rujuk\nPustu/Puskesmas");
        $styleCenterWrap("AY{$headerTopRow}:AY{$optionRowEnd}");

        // Baris 20 & 21 SKILAS
        $allSkilasHeaders = [
            'AK' => 'Orientasi waktu / tempat',
            'AL' => 'Mengulang 3 kata',
            'AM' => 'Tes berdiri dari kursi',
            'AN' => 'BB berkurang >3kg dalam  3 bulan terakhir atau pakaian jadi lebih longgar',
            'AO' => 'Hilang nafsu makan / kesulitan makan',
            'AP' => 'LiLA < 21 cm',
            'AQ' => 'Masalah pada mata (sulit lihat jauh, membaca, penyakit mata, sedang dalam pengobatan Hipertensi/diabetes)',
            'AR' => 'Tes melihat (huruf kecil)',
            'AS' => 'Tes Bisik',  // akan di-merge dengan AT
            'AT' => '',                     // kanan merge
            'AU' => 'Perasaan sedih, tertekan, atau putus asa',
            'AV' => 'Sedikit minat atau kesenangan dalam melakukan sesuatu',
            'AW' => "Imunisasi COVID 19\nYa/Tidak",
            'AX' => 'Catatan / edukasi',
            'AY' => 'Rujuk Pustu/Puskesmas',
        ];

        // Baris 20 → judul indikator
        foreach ($allSkilasHeaders as $col => $title) {

            $range = null;

            // ===== CASE MERGE KHUSUS =====
            if ($col === 'AS') {
                $range = "AS{$optionRowStart}:AT{$optionRowStart}";
                $sheet->mergeCells($range);
                $sheet->setCellValue("AS{$optionRowStart}", $title);

            } elseif (in_array($col, ['AW','AX','AY'])) {
                $range = "{$col}{$r(16)}:{$col}{$optionRowStart}";
                $sheet->mergeCells($range);
                $sheet->setCellValue("{$col}{$r(16)}", $title);

            } elseif ($col === 'AT') {
                continue; // ikut merge AS

            } else {
                // ===== CASE NORMAL =====
                $range = "{$col}{$optionRowStart}";
                $sheet->setCellValue($range, $title);
            }

            // ===== STYLE UMUM (SATU PINTU) =====
            if ($range) {
                $style = $sheet->getStyle($range);

                $style->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);

                $style->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
            }
        }

        // Baris 21 → keterangan "Ya / Tidak" dll
        foreach ($allSkilasHeaders as $col => $title) {
            $range = $col . $optionRowEnd;

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
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);
            $sheet->getStyle($range)->getFont()->setSize(10);
            $sheet->getStyle($range)->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);
        }

        // Merge kolom ringkasan (AY20–AY21)
        $sheet->mergeCells("AY" . $optionRowStart . ":AY" . $optionRowEnd);
        $sheet->getStyle("AY" . $optionRowStart . ":AY" . $optionRowEnd)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        // // Warna background
        $colorSkilasHeader   = 'FFD7E1F3';
        $colorTanggalSKILAS  = 'FFFCE2D2';
        $colorEdukasiSKILAS  = 'FFCCCCFF';

        $sheet->getStyle("AJ" . $optionRowStart . ":AJ" . $optionRowEnd)
            ->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB($colorTanggalSKILAS);
        $sheet->getStyle("AX" . $r(16) . ":AX" . $optionRowEnd)
            ->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB($colorEdukasiSKILAS);

        $headerRangesSKILAS = ['AK'.$r(16).':AL'.$optionRowEnd, 'AM'.$headerTopRow.':AM'.$optionRowEnd, 'AN'.$headerTopRow.':AP'.$optionRowEnd,
                         'AQ'.$headerTopRow.':AR'.$optionRowEnd, 'AS'.$headerTopRow.':AT'.$optionRowEnd, 'AU'.$headerTopRow.':AV'.$optionRowEnd,
                         'AW'.$r(16).':AW'.$optionRowEnd, 'AX'.$headerTopRow.':AX'.$optionRowEnd, 'AY'.$r(16).':AY'.$optionRowEnd];

        foreach ($headerRangesSKILAS as $range) {
            $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($colorSkilasHeader);
        }

        $sheet->getStyle("AX{$headerTopRow}:AX{$optionRowEnd}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFCCCCFF');

        // ====================== ISI DATA ======================
        $dataRow = $aksFirstDataRow;
        foreach ($periksas as $periksa) {
            $sheet->setCellValue("A{$dataRow}", Carbon::parse($periksa->tanggal_periksa)->format('d-m-Y'));

            $aksMap = [
                'B' => 'aks_bab_s0_tidak_terkendali', 'C' => 'aks_bab_s1_kadang_tak_terkendali', 'D' => 'aks_bab_s2_terkendali',
                'E' => 'aks_bak_s0_tidak_terkendali_kateter', 'F' => 'aks_bak_s1_kadang_1x24jam', 'G' => 'aks_bak_s2_mandiri',
                'H' => 'aks_diri_s0_butuh_orang_lain', 'I' => 'aks_diri_s1_mandiri',
                'J' => 'aks_wc_s0_tergantung_lain', 'K' => 'aks_wc_s1_perlu_beberapa_bisa_sendiri', 'L' => 'aks_wc_s2_mandiri',
                'M' => 'aks_makan_s0_tidak_mampu', 'N' => 'aks_makan_s1_perlu_pemotongan', 'O' => 'aks_makan_s2_mandiri',
                'P' => 'aks_bergerak_s0_tidak_mampu', 'Q' => 'aks_bergerak_s1_butuh_2orang', 'R' => 'aks_bergerak_s2_butuh_1orang', 'S' => 'aks_bergerak_s3_mandiri',
                'T' => 'aks_jalan_s0_tidak_mampu', 'U' => 'aks_jalan_s1_kursi_roda', 'V' => 'aks_jalan_s2_bantuan_1orang', 'W' => 'aks_jalan_s3_mandiri',
                'X' => 'aks_pakaian_s0_tergantung_lain', 'Y' => 'aks_pakaian_s1_sebagian_dibantu', 'Z' => 'aks_pakaian_s2_mandiri',
                'AA' => 'aks_tangga_s0_tidak_mampu', 'AB' => 'aks_tangga_s1_butuh_bantuan', 'AC' => 'aks_tangga_s2_mandiri',
                'AD' => 'aks_mandi_s0_tergantung_lain', 'AE' => 'aks_mandi_s1_mandiri',
            ];
            foreach ($aksMap as $col => $field) {
                $val = $periksa->{$field} ?? 0;
                $sheet->setCellValue("{$col}{$dataRow}", $val ? 1 : '');
            }
            $sheet->setCellValue("AF{$dataRow}", $periksa->aks_kategori ?? '');
            $sheet->setCellValue("AG{$dataRow}", $periksa->aks_edukasi ?? '');
            $sheet->setCellValue("AH{$dataRow}", $periksa->aks_perlu_rujuk ? 'YA' : 'TIDAK');

            $sheet->setCellValue("AJ{$dataRow}", Carbon::parse($periksa->tanggal_periksa)->format('d-m-Y'));

            $skMap = [
                'AK' => 'skil_orientasi_waktu_tempat', 'AL' => 'skil_mengulang_ketiga_kata', 'AM' => 'skil_tes_berdiri_dari_kursi',
                'AN' => 'skil_bb_berkurang_3kg_dalam_3bulan', 'AO' => 'skil_hilang_nafsu_makan', 'AP' => 'skil_lla_kurang_21cm',
                'AQ' => 'skil_masalah_pada_mata', 'AR' => 'skil_tes_melihat', 'AS' => 'skil_tes_bisik', 'AT' => 'skil_tidak_dapat_dilakukan',
                'AU' => 'skil_perasaan_sedih_tertekan', 'AV' => 'skil_sedikit_minat_atau_kenikmatan', 'AW' => 'skil_imunisasi_covid',
            ];
            foreach ($skMap as $col => $field) {
                $val = $periksa->{$field} ?? 0;
                $sheet->setCellValue("{$col}{$dataRow}", $val ? 'Ya' : 'Tidak');
            }
            $sheet->setCellValue("AX{$dataRow}", $periksa->skil_edukasi ?? '');
            $sheet->setCellValue("AY{$dataRow}", $periksa->skil_rujuk_otomatis ? 'Ya' : 'Tidak');

            $dataRow++;
        }

        $lastDataRow = $dataRow - 1;

        // =====================================================================
        // BARIS 22 → NOMOR KOLOM AKS + SKILAS
        // =====================================================================
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
        
        // Style header nomor kolom (row 22)
        $styleHeaderNo = $sheet->getStyle("A" . $r(22) . ":AY" . $r(22));
        $styleHeaderNo->getFont()->setBold(true);
        $styleHeaderNo->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Background abu-abu
        $sheet->getStyle("A" . $r(22) . ":AH" . $r(22))
            ->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFBFBFBF');

        $sheet->getStyle("AJ" . $r(22) . ":AY" . $r(22))
            ->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFBFBFBF');

        // =====================================================================
        // 6. STYLING AKHIR
        // =====================================================================
        $sheet->getStyle("A{$headerTopRow}:AY{$lastDataRow}")
            ->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        $sheet->getStyle("A".$r(16).":AY".$r(22))
            ->getFont()->setBold(true);

        $sheet->getStyle("A".$r(16).":AH".$r(22))
            ->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THICK)
            ->getColor()->setRGB('FFFFFF');

        $sheet->getStyle("AI".$r(16).":AY".$r(22))
            ->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THICK)
            ->getColor()->setRGB('FFFFFF');

        $sheet->mergeCells("AI" . $r(1) . ":AI{$lastDataRow}");

        $sheet->getStyle("AI" . $r(1) . ":AI{$lastDataRow}")
            ->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF00B050');

        $sheet->getStyle("AI" . $r(1) . ":AI{$lastDataRow}")
            ->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THICK)
            ->getColor()->setRGB('FFFFFF');

        $styleAll = $sheet->getStyle("A{$aksFirstDataRow}:AY{$lastDataRow}");

        $styleAll->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        $styleAll->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        $sheet->getDefaultRowDimension()->setRowHeight(22);
        $sheet->getRowDimension($r(17))->setRowHeight(40);
        $sheet->getRowDimension($optionRowStart)->setRowHeight(70);
        $sheet->getRowDimension($optionRowEnd)->setRowHeight(60);

        return $lastDataRow;
    }
}
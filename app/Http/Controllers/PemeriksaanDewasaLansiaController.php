<?php

namespace App\Http\Controllers;

use App\Models\Warga;
use App\Models\PemeriksaanDewasaLansia;
use Illuminate\Http\Request;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Style\Color;

class PemeriksaanDewasaLansiaController extends Controller
{
    public function index()
    {
        return view('page.dewasa.index');
    }

    public function data()
    {
        $warga = Warga::whereRaw('TIMESTAMPDIFF(YEAR, tanggal_lahir, CURDATE()) >= 15')
            ->select('id', 'nik', 'nama', 'tanggal_lahir')
            ->with('pemeriksaanDewasaLansiaTerakhir') // ⬅ TANPA select kolom di sini
            ->get();

        $data = $warga->map(function ($w) {
            $lahir = Carbon::parse($w->tanggal_lahir);
            $diff  = $lahir->diff(now());
            $tahun = $diff->y;
            $bulan = $diff->m;
            $umur  = $tahun > 0 ? "$tahun thn $bulan bln" : "$bulan bln";

            // relasi hasOne → langsung object, bukan collection
            $p = $w->pemeriksaanDewasaLansiaTerakhir;

            return [
                'id'   => $w->id,
                'nik'  => $w->nik,
                'nama' => $w->nama,
                'umur' => helper_umur($w->tanggal_lahir),

                'terakhir' => $p?->tanggal_periksa
                    ? $p->tanggal_periksa->format('d/m/Y')
                    : '<span class="text-red-600 font-bold">Belum pernah</span>',

                'imt' => $p?->imt_badge_html ?? '<span class="text-gray-500">-</span>',
                'td'  => $p?->td_badge_html  ?? '<span class="text-gray-500">-</span>',

                'puma' => $p?->skor_puma !== null
                    ? ($p->skor_puma >= 6
                        ? '<span class="text-red-600 font-bold">'.$p->skor_puma.'</span>'
                        : $p->skor_puma)
                    : '<span class="text-gray-500">-</span>',

                'tbc' => $p?->tbc_rujuk
                    ? '<span class="text-red-600 font-bold">YA</span>'
                    : '<span class="text-gray-500">-</span>',

                'rujuk' => ($p?->tbc_rujuk || $p?->rujuk_puskesmas)
                    ? '<span class="text-red-600 font-bold text-lg">RUJUK!</span>'
                    : '<span class="text-green-600 font-medium">Aman</span>',

                'periksa_id' => $p?->id,
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function form(Warga $warga)
    {
        // Cek usia
        if ($warga->tanggal_lahir) {
            $usia = helper_umur($warga->tanggal_lahir);
            if ($usia < 15) {
                return response()->json([
                    'error' => 'Hanya untuk warga usia ≥15 tahun. Usia saat ini: ' . $usia . ' tahun.'
                ], 403);
            }
        } else {
            return response()->json(['error' => 'Tanggal lahir tidak valid'], 400);
        }

        // KIRIM SEBAGAI FRAGMENT HTML DENGAN HEADER YANG BENAR!
        $html = view('page.dewasa.form', compact('warga'))->render();

        return response($html)
            ->header('Content-Type', 'text/html')
            ->header('X-Fragment', 'true'); // opsional, buat jaga-jaga
    }

    // Controller: DewasaController (contoh)
    public function riwayat(Warga $warga)
    {
        // Ambil semua pemeriksaan milik warga (sesuaikan nama relasi)
        $periksas = $warga->pemeriksaanDewasaLansiaAll()->get(); // or pemeriksaanLansiaAll()

        // Pastikan setiap periksa mengandung info warga untuk kemudahan JS (nama, nik, usia)
        // Kita convert ke array agar safe ke JSON di blade
        $riwayat = $periksas->map(function($p) use ($warga) {
            $arr = $p->toArray();

            // tambahan: beberapa label/human fields yang sering dipakai di JS
            $arr['warga'] = [
                'id' => $warga->id,
                'nama' => $warga->nama,
                'nik'  => $warga->nik,
                'usia' => method_exists($warga, 'getUsiaAttribute') ? $warga->usia : ( $warga->tanggal_lahir ? \Carbon\Carbon::parse($warga->tanggal_lahir)->age : null ),
            ];

            // jika ingin tambahan label mudah (opsional)
            $arr['tanggal_periksa_formatted'] = isset($p->tanggal_periksa) ? $p->tanggal_periksa->format('Y-m-d') : null;
            $arr['petugas_name'] = $p->petugas_name ?? ($p->petugas?->name ?? null);

            return $arr;
        });

        return response()->view('page.dewasa.riwayat', compact('warga', 'riwayat'));
    }


    public function storeAjax(Request $request, Warga $warga)
    {
        $request->validate([
            'tanggal_periksa' => 'required|date',
            'berat_badan'     => 'required|numeric|min:20|max:300',
            'tinggi_badan'    => 'required|numeric|min:100|max:250',
            'lingkar_perut'   => 'required|numeric',
            'lingkar_lengan_atas' => 'required|numeric',
            'sistole'         => 'required|numeric',
            'diastole'        => 'required|numeric',
            'gula_darah'      => 'required|numeric',
            'merokok'         => 'required|in:0,1,2',
        ]);

        // === AMBIL SEMUA INPUT ===
        $input = $request->all();

        // === PASTIKAN jk_puma & usia_puma ANGKA ===
        $input['jk_puma']   = (int)($input['jk_puma']   ?? 0);  // misal: 1 = laki, 0 = perempuan
        $input['usia_puma'] = (int)($input['usia_puma'] ?? 0);  // misal: 1 = usia risiko, 0 = tidak

        // === 1. BERSIHKAN CHECKBOX PUMA: "on" → 1, tidak ada → 0 ===
        $pumaFields = ['puma_napas_pendek', 'puma_dahak', 'puma_batuk', 'puma_spirometri'];
        foreach ($pumaFields as $field) {
            $input[$field] = $request->has($field) ? 1 : 0;
        }

        // === 1b. HITUNG SKOR PUMA ===
        $input['skor_puma'] =
            ($input['puma_napas_pendek'] ?? 0) +
            ($input['puma_dahak']        ?? 0) +
            ($input['puma_batuk']        ?? 0) +
            ($input['puma_spirometri']   ?? 0) +
            ($input['usia_puma']         ?? 0) +
            ($input['jk_puma']           ?? 0) +
            ($input['merokok']           ?? 0);

        // === 2. BERSIHKAN KATEGORI IMT: "Normal (N)" → "N" dll ===
        if (isset($input['kategori_imt'])) {
            $map = [
                'Normal (N)'         => 'N',
                'Sangat Kurus (SK)'  => 'SK',
                'Kurus (K)'          => 'K',
                'Gemuk (G)'          => 'G',
                'Obesitas (O)'       => 'O',
            ];
            $input['kategori_imt'] = $map[$input['kategori_imt']] ?? 'N';
        }

        // === 3. TD KATEGORI: "Tinggi (T)" → "T", selain itu → "N" ===
        $input['td_kategori'] = (str_contains($input['td_kategori'] ?? '', 'T')) ? 'T' : 'N';

        // === 4. GULA DARAH KATEGORI: "Tinggi (T)" → "T", selain itu → "N" ===
        $input['gula_kategori'] = (str_contains($input['gula_kategori'] ?? '', 'T')) ? 'T' : 'N';

        // === 5. MATA & TELINGA: "Normal" → "N", "Gangguan" → "G" ===
        $mataTelinga = ['mata_kanan', 'mata_kiri', 'telinga_kanan', 'telinga_kiri'];
        foreach ($mataTelinga as $field) {
            if (isset($input[$field])) {
                $input[$field] = $input[$field] === 'Normal' ? 'N' : 'G';
            }
        }

        // === 6. MEROKOK: pastikan angka ===
        $input['merokok'] = (int) ($input['merokok'] ?? 0);

        // === 7. TBC: pastikan Ya/Tidak ===
        $tbcFields = ['tbc_batuk', 'tbc_demam', 'tbc_bb_turun', 'tbc_kontak'];
        foreach ($tbcFields as $field) {
            $input[$field] = in_array($input[$field] ?? '', ['Ya', 'Tidak']) ? $input[$field] : 'Tidak';
        }

        // === 8. RUJUK PUSKESMAS ===
        $input['rujuk_puskesmas'] = $request->has('rujuk_puskesmas');

        // === 9. HITUNG USIA OTOMATIS (BERTAHUN) ===
        $tglPeriksa = Carbon::parse($request->tanggal_periksa);
        $input['usia'] = helper_umur($warga->tanggal_lahir);

        // === 10. SIMPAN DATA ===
        $periksa = $warga->pemeriksaanDewasaLansia()->updateOrCreate(
            [
                'warga_id'        => $warga->id,
                'tanggal_periksa' => $tglPeriksa->format('Y-m-d'),
            ],
            $input
        );

        return response()->json([
            'success' => 'Pemeriksaan berhasil disimpan!',
            'data'    => $periksa
        ]);
    }

    public function updateAjax(Request $request, Warga $warga)
    {
        return $this->storeAjax($request, $warga);
    }

    public function editAjax(Warga $warga, PemeriksaanDewasaLansia $periksa)
    {
        $html = view('page.dewasa.form', compact('warga', 'periksa'))->render();

        return response($html)
            ->header('Content-Type', 'text/html')
            ->header('X-Fragment', 'true');
    }

    public function destroyAjax(PemeriksaanDewasaLansia $periksa)
    {
        $periksa->delete();
        return response()->json(['success' => 'Data berhasil dihapus!']);
    }

    public function export(Warga $warga)
    {
        $filename = 'pemeriksaan_dewasa_' . \Illuminate\Support\Str::slug($warga->nama) . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\DewasaTemplateExport($warga),
            $filename
        );
    }


    // 1 FILE 1 DATA WARGA DEWASA & LANSIA BESERTA SEMUA PEMERIKSAANNYA
    // ! HALMAN 1 KARTU
    public function exportKartuExcelSatuan(Warga $warga)
    {
        // AMBIL SEMUA RIWAYAT PEMERIKSAAN WARGA INI
        $periksas = $warga->pemeriksaanDewasaLansiaAll; // collection

        if ($periksas->isEmpty()) {
            abort(404, 'Belum ada data pemeriksaan');
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
        // LEBAR KOLOM
        // =====================================================================
        $columnWidths = [
            'A'  => 20,
            'B'  => 10, 'C'  => 10, 'D'  => 10,
            'E'  => 9,  'F'  => 9,  'G'  => 9,  'H'  => 9,  'I'  => 9,
            'J'  => 9,  'K'  => 9,  'L'  => 9,  'M'  => 9,  'N'  => 9,
            'O'  => 9,  'P'  => 9,  'Q'  => 9,  'R'  => 13,  'S'  => 13,
            'T'  => 13, 'U'  => 13,  'V'  => 9,  'W'  => 9,  'X'  => 9,
            'Y'  => 9,  'Z'  => 9,
            'AA' => 13, 'AB' => 13, 'AC' => 9,
            // kalau mau atur lebar SKILAS bisa tambah AI–AY di sini
        ];
        foreach ($columnWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // Default row height untuk semua baris
        // $sheet->getDefaultRowDimension()->setRowHeight(22);

        // =====================================================================
        // 1. HEADER UTAMA & IDENTITAS AKS
        // =====================================================================

        // ALIGNMENT
        $sheet->getStyle('A1:AC1')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        // BACKGROUND
        $sheet->getStyle('A1:AC1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF00B050'); // hijau 00B050

        $sheet->setCellValue('A2', 'KARTU BANTU PEMERIKSAAN LANSIA (≥60 Tahun)');
        $sheet->mergeCells('A2:AC2');
        $sheet->getStyle('A2:AC2')->getFont()->setSize($fontSizeHeaderUtama)->setBold(true);
        $sheet->getStyle('A2:AC2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A3', 'POSYANDU TAMAN CIPULIR ESTATE');
        $sheet->mergeCells('A3:AC3');
        $sheet->getStyle('A3:AC3')->getFont()->setSize($fontSizeHeaderUtama)->setBold(true);
        $sheet->getStyle('A3:AC3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

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

        // Tanda titik dua di kolom C
        foreach (['C5','C6','C7','C8','C9','C10','C11','C12','C13','C14'] as $cell) {
            $sheet->setCellValue($cell, ':');
            $sheet->getStyle($cell)->getFont()->setSize(12);
            $sheet->getStyle($cell)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                ->setVertical(Alignment::VERTICAL_CENTER);
        }

        // ==================== ISI IDENTITAS ====================
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

        foreach ($dataIdentitas as $rowIdentitas => $value) {

            // merge kolom D–F
            $sheet->mergeCells("D{$rowIdentitas}:F{$rowIdentitas}");
            $sheet->setCellValue("D{$rowIdentitas}", $value ?? '-');

            $sheet->getStyle("D{$rowIdentitas}:F{$rowIdentitas}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);
        }

        // ==================== JENIS KELAMIN (RichText) ====================
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

        $sheet->setCellValue('G5', $richText);

        // ==================== UMUR ====================
        $tahun = $warga->tanggal_lahir
            ? helper_umur($warga->tanggal_lahir) : 0;

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
        // teks riwayat
        $riwayatKeluargaItems = [
            'a. Hipertensi',
            'b. DM',
            'c. Stroke',
            'd. Jantung',
            'f. Kanker',
            'g. Kolesterol Tinggi',
        ];

        $row5 = 5; // baris untuk keluarga

        // S-T  (TIDAK MERGE)
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

        // AC-AD (HANYA INI DI MERGE)
        $sheet->mergeCells("X{$row5}:Y{$row5}");
        $sheet->setCellValue("X{$row5}", $riwayatKeluargaItems[5]);
        $sheet->getStyle("X{$row5}:Y{$row5}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

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

        $row7 = $row5 + 2; // Baris 7

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

        // AC-AD (HANYA INI DI MERGE)
        $sheet->mergeCells("X{$row7}:Y{$row7}");
        $sheet->setCellValue("X{$row7}", $riwayatDiriItems[5]);
        $sheet->getStyle("X{$row7}:Y{$row7}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);


        $row9  = $row7 + 2; // Baris 9
        $row10 = $row9 + 1; // Baris 10
        $row11 = $row10 + 1; // Baris 11
        $row12 = $row9 + 3; // Baris 12

        // ---------------- PERILAKU BERISIKO ----------------
        $sheet->mergeCells("Q{$row9}:R{$row12}");
        $sheet->setCellValue("Q{$row9}", "Perilaku Berisiko Diri Sendiri\n(lingkari jika ada)");
        $sheet->getStyle("Q{$row9}:R{$row12}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setWrapText(true);

        // merge isi perilaku (S–V menjadi T–W → digeser 1 jadi S–V)
        foreach ([9,10,11,12] as $row7) {
            $sheet->mergeCells("S{$row7}:V{$row7}");
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

        // YA/TIDAK: X–Y → digeser menjadi Y–Z
        foreach ([9,10,11,12] as $row7) {
            $sheet->mergeCells("Y{$row7}:Z{$row7}");
        }

        $sheet->setCellValue("Y{$row9}", ': Ya/Tidak');
        $sheet->setCellValue("Y{$row10}", ': Ya/Tidak');
        $sheet->setCellValue("Y{$row11}", ': Ya/Tidak');
        $sheet->setCellValue("Y{$row12}", ': Ya/Tidak');

        $sheet->getStyle("Y{$row9}:Z{$row12}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        // styling per area baru
        $sheet->getStyle("Q{$row5}:AD{$row12}")->getFont()->setSize($fontSizeHeaderKecil);
        $sheet->getStyle("Q{$row5}:AD{$row12}")->getAlignment()
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setWrapText(true);


            // Langkah-langkah di AC8–AC12
        $steps = [
            ['AB','AC', 8,  ': Diisi langkah 1', 'FFFCE2D2'],
            ['AB','AC', 9,  ': Diisi langkah 2', 'FFFFE79B'],
            ['AB','AC', 10, ': Diisi langkah 3', 'FFFFFFCC'],
            ['AB','AC', 11, ': Diisi langkah 4', 'FFD7E1F3'],
            ['AB','AC', 12, ': Diisi langkah 5', 'FFCCCCFF'],
        ];

        foreach ($steps as [$col1, $col2, $row, $text, $color]) {
            $sheet->mergeCells("{$col1}{$row}:{$col2}{$row}");
            $sheet->setCellValue("{$col1}{$row}", $text);

            $sheet->getStyle("{$col1}{$row}:{$col2}{$row}")
                ->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB($color);

            $sheet->getStyle("{$col1}{$row}:{$col2}{$row}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);
        }

        $sheet->getStyle("P{$row5}:AA{$row12}")->getFont()->setSize(11);
        $sheet->getStyle("P{$row5}:AA{$row12}")->getAlignment()
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setWrapText(true);

        $row16 = $row12 + 4; // Baris 16
        $row17 = $row16 + 1; // Baris 17
        $row18 = $row17 + 1; // Baris 18
        $row19 = $row18 + 1; // Baris 19
        $row20 = $row19 + 1; // Baris 20

        // ==================== HEADER ATAS ====================
        $sheet->setCellValue("A{$row16}", 'Usia Dewasa dan Lansia');
        $sheet->mergeCells("A{$row16}:Z{$row16}");
        $sheet->getStyle("A{$row16}:Z{$row16}")->getFont()->setSize(16)->setBold(true);
        $sheet->getStyle("A{$row16}:Z{$row16}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // ==================== KOLOM A: WAKTU KE POSYANDU ====================
        $sheet->mergeCells("A{$row18}:A{$row20}");
        $sheet->setCellValue("A{$row18}", "Waktu ke\nPosyandu\n(tanggal/bulan/tahun)");
        $sheet->getStyle("A{$row18}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row18}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        // ==================== BARIS 17 — HEADER BESAR ====================
        $sheet->mergeCells("A{$row17}:N{$row17}");
        $sheet->setCellValue("A{$row17}", "Hasil Penimbangan / Pengukuran / Pemeriksaan\n(Jika hasil pemeriksaan Tekanan Darah/Gula Darah tergolong tinggi maka dirujuk ke Pustu/Puskesmas)");
        $sheet->getStyle("A{$row17}:N{$row17}")->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle("A{$row17}:N{$row17}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        $sheet->mergeCells("O{$row17}:V{$row17}");
        $sheet->setCellValue("O{$row17}","kuesioner PPOK/PUMA (Skoring) ≥ 40 Tahun dan merokok\n(jika sasaran menjawab dengan score >6 , maka sasaran dirujuk ke Pustu/Puskesmas)");
        $sheet->getStyle("O{$row17}:V{$row17}")->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle("O{$row17}:V{$row17}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        $sheet->mergeCells("W{$row17}:Z{$row17}");
        $sheet->setCellValue("W{$row17}",'Hasil Wawancara Faktor Risiko PM');
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
        $sheet->mergeCells("B{$row18}:B{$row20}"); $sheet->setCellValue("B{$row18}", "Berat\nBadan\n(Kg)");
        $sheet->mergeCells("C{$row18}:C{$row20}"); $sheet->setCellValue("C{$row18}", "Tinggi\nBadan\n(Cm)");
        $sheet->mergeCells("D{$row18}:D{$row20}"); $sheet->setCellValue("D{$row18}", "IMT\nSangat Kurus (SK)/\nKurus (K)/\nNormal (N)/\nGemuk (G)/\nObesitas (O)");
        $sheet->mergeCells("E{$row18}:E{$row20}"); $sheet->setCellValue("E{$row18}", "Lingkar\nPerut\n(Cm)");
        $sheet->mergeCells("F{$row18}:F{$row20}"); $sheet->setCellValue("F{$row18}", "Lingkar\nLengan\nAtas\n(Cm)");
        $sheet->mergeCells("G{$row18}:H18"); $sheet->setCellValue("G{$row18}", 'Tekanan Darah');
        $sheet->mergeCells("G{$row19}:G{$row20}"); $sheet->setCellValue("G{$row19}", "Sistole/\nDiastole");
        $sheet->mergeCells("H{$row19}:H{$row20}"); $sheet->setCellValue("H{$row19}", "Hasil\n(Rendah/\nNormal/\nTinggi)");
        $sheet->mergeCells("I{$row18}:J{$row18}"); $sheet->setCellValue("I{$row18}", 'Gula Darah');
        $sheet->mergeCells("I{$row19}:I{$row20}"); $sheet->setCellValue("I{$row19}", "Kadar\nGula Darah\nSewaktu\nmg/dL");
        $sheet->mergeCells("J{$row19}:J{$row20}"); $sheet->setCellValue("J{$row19}", "Hasil\n(Rendah/\nNormal/\nTinggi)");
        $sheet->mergeCells("K{$row18}:L18"); $sheet->setCellValue("K{$row18}", 'Tes Hitung Jari Tangan');
        $sheet->setCellValue("K{$row19}", 'Mata Kanan'); $sheet->setCellValue("L{$row19}", 'Mata Kiri');
        $sheet->setCellValue("K{$row20}", "Normal/\nGangguan"); $sheet->setCellValue("L{$row20}", "Normal/\nGangguan");
        $sheet->mergeCells("M{$row18}:N{$row18}"); 
        $sheet->setCellValue("M{$row18}", 'Tes Berbisik');
        $sheet->setCellValue("M{$row19}", "Telinga\nKanan"); $sheet->setCellValue("N{$row19}", "Telinga\nKiri");
        $sheet->setCellValue("M{$row20}", "Normal/\nGangguan"); $sheet->setCellValue("N{$row20}", "Normal/\nGangguan");

        // ==================== KUESIONER PPOK/PUMA (O–V) ====================
        $sheet->setCellValue("O{$row18}", "Jenis\nKelamin");
        $sheet->setCellValue("P{$row18}", "Usia");
        $sheet->setCellValue("Q{$row18}", "Merokok");
        $sheet->mergeCells("R{$row18}:R{$row20}");
        $sheet->setCellValue("R{$row18}", "Apakah Anda sering merasa\nnapas pendek saat berjalan\ncepat di jalan datar atau\nsedikit menanjak?\n\n(Tidak = 0 | Ya = 1)");
        $sheet->mergeCells("S{$row18}:S{$row20}");
        $sheet->setCellValue("S{$row18}", "Apakah Anda sering\nmempunyai dahak dari paru\natau sulit mengeluarkan\ndahak saat tidak flu?\n\n(Tidak = 0 | Ya = 1)");
        $sheet->mergeCells("T{$row18}:T{$row20}");
        $sheet->setCellValue("T{$row18}", "Apakah Anda biasanya\nbatuk saat tidak sedang\nmenderita flu?\n\n(Tidak = 0 | Ya = 1)");
        $sheet->mergeCells("U{$row18}:U{$row20}");
        $sheet->setCellValue("U{$row18}", "Pernahkah dokter/tenaga\nkesehatan meminta Anda\nmeniup alat spirometri\natau peakflow meter?\n\n(Tidak = 0 | Ya = 1)");
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
        $sheet->setCellValue("W{$row18}", "Skrining Gejala TBC (jika 2 gejala terpenuhi maka dirujuk ke Puskesmas)");
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

        // ==================== ISI DATA (BARIS 21, LOOP SEMUA RIWAYAT) ====================
        
        $row21 = $row20 + 1; //Baris 21

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

        $sheet->getStyle("A{$row21}:AC{$row21}")->getFont()->setBold(true);

        $sheet->getStyle("A{$row21}:AC{$row21}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB("FFBFBFBF");

        // =====================================================================

        $row22 = $row21 + 1;

        foreach ($periksas as $periksa) {
            // Tanggal
            $sheet->setCellValue("A{$row22}", Carbon::parse($periksa->tanggal_periksa)->translatedFormat('d F Y') ?? '-');

            // IMT & kategori
            $imt = ($periksa->tinggi_badan > 0)
                ? round($periksa->berat_badan / (($periksa->tinggi_badan / 100) ** 2), 2)
                : 0;

            $kategori = $imt < 17   ? 'SK'
                      : ($imt < 18.5 ? 'K'
                      : ($imt < 25   ? 'N'
                      : ($imt < 30   ? 'G' : 'O')));


            $sheet->setCellValue("B{$row22}", $periksa->berat_badan ?? '');
            $sheet->setCellValue("C{$row22}", $periksa->tinggi_badan ?? '');
            $sheet->setCellValue("D{$row22}", $imt);
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

            $sheet->setCellValue("K{$row22}", $periksa->mata_kanan === 'G' ? 'Gangguan' : 'Normal');
            $sheet->setCellValue("L{$row22}", $periksa->mata_kiri === 'G' ? 'Gangguan' : 'Normal');
            $sheet->setCellValue("M{$row22}", $periksa->telinga_kanan === 'G' ? 'Gangguan' : 'Normal');
            $sheet->setCellValue("N{$row22}", $periksa->telinga_kiri === 'G' ? 'Gangguan' : 'Normal');

            // PUMA
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

            // TBC
            $sheet->setCellValue("W{$row22}", $periksa->tbc_batuk       ?? 'Tidak');
            $sheet->setCellValue("X{$row22}", $periksa->tbc_demam       ?? 'Tidak');
            $sheet->setCellValue("Y{$row22}", $periksa->tbc_bb_turun    ?? 'Tidak');
            $sheet->setCellValue("Z{$row22}", $periksa->tbc_kontak_erat ?? 'Tidak');
            $sheet->setCellValue("AA{$row22}", $periksa->kontrasepsi ?? '-');
            $sheet->setCellValue("AB{$row22}", $periksa->edukasi     ?? '-');

            // Auto rujuk
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
            ])->filter(fn ($v) => $v === 'Ya')->count();

            if ($gejalaTBC >= 2) {
                $rujuk[] = 'Suspek TBC';
            }

            if (!empty($rujuk)) {
                $sheet->setCellValue("AC{$row22}", 'YA (' . implode(', ', $rujuk) . ')');
                $sheet->getStyle("AC{$row22}")
                    ->getFont()->setBold(true)->getColor()->setARGB('FFFF0000');
            } else {
                // 0 = Tidak, 1 = Ya
                $sheet->setCellValue(
                    "AC{$row22}",
                    ($periksa->rujuk_puskesmas == 1 ? 'Ya' : 'Tidak')
                );
            }

            $row22++; // pindah ke baris riwayat berikutnya
        }

        $lastRow = $row22 - 1;

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


        foreach (array_merge(range('A', 'Z'), ['AA', 'AB', 'AC']) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(false);
        }

        $sheet->getRowDimension(17)->setRowHeight(45);
        $sheet->getRowDimension(18)->setRowHeight(75);
        $sheet->getRowDimension(19)->setRowHeight(50);
        $sheet->getRowDimension(20)->setRowHeight(50);

        // ==================== DOWNLOAD ====================
        $filename = "Kartu_Pemeriksaan_Dewasa_Lansia_{$warga->nik}.xlsx";
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    // 1 FILE BANYAK DATA WARGA DEWASA & LANSIA BESERTA SEMUA PEMERIKSAAN
    // ! HALMAN BANYAK KARTU
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
            'T'  => 13, 'U'  => 13,  'V'  => 9,  'W'  => 9,  'X'  => 9,
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

    public function exportKartuExcelSemua()
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
}
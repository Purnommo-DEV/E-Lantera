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
                'umur' => $umur,

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
            $lahir = Carbon::parse($warga->tanggal_lahir);
            $usia = $lahir->age; // lebih simpel!
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

    public function riwayat(Warga $warga)
    {
        $riwayat = $warga->pemeriksaanDewasaLansiaAll()
            ->orderByDesc('tanggal_periksa')
            ->get();

        $html = view('page.dewasa.riwayat', compact('warga', 'riwayat'))->render();

        return response($html)->header('Content-Type', 'text/html');
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
        $lahir      = Carbon::parse($warga->tanggal_lahir);
        $input['usia'] = $lahir->diffInYears($tglPeriksa);

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


    // 1 FILE 1 WARGA BESERTA SEMUA PEMERIKSAANNYA
    public function exportKartuExcel(Warga $warga)
    {
        // AMBIL SEMUA RIWAYAT PEMERIKSAAN WARGA INI
        $periksas = $warga->pemeriksaanDewasaLansiaAll; // collection

        if ($periksas->isEmpty()) {
            abort(404, 'Belum ada data pemeriksaan');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // ==================== JUDUL ====================
        $sheet->setCellValue('A2', 'KARTU BANTU PEMERIKSAAN USIA DEWASA DAN LANSIA (>19 Tahun)');
        $sheet->mergeCells('A2:AC2');
        $sheet->getStyle('A2:AC2')->getFont()->setSize(16)->setBold(true);
        $sheet->getStyle('A2:AC2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A3', 'POSYANDU TAMAN CIPULIR ESTATE');
        $sheet->mergeCells('A3:AC3');
        $sheet->getStyle('A3:AC3')->getFont()->setSize(16)->setBold(true);
        $sheet->getStyle('A3:AC3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // ==================== IDENTITAS ====================
        $labelsA = [
            'A5'  => 'Nama',
            'A6'  => 'NIK',
            'A7'  => 'Tanggal Lahir',
            'A8'  => 'Alamat',
            'A9'  => 'No. HP',
            'A10' => 'Status Perkawinan',
            'A11' => 'Pekerjaan',
            'A12' => 'Dusun/RT/RW',
            'A13' => 'Kecamatan',
            'A14' => 'Desa/Kelurahan/Nagari'
        ];

        foreach ($labelsA as $cell => $text) {
            $sheet->setCellValue($cell, $text);
            $style = $sheet->getStyle($cell);
            $style->getFont()->setSize(16)->setBold(true);
            $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
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
            $sheet->setCellValue($cell, $text);
            $style = $sheet->getStyle($cell);
            $style->getFont()->setSize(12)->setBold(false);
            $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }

        // ==================== RIWAYAT KELUARGA / DIRI SENDIRI / PERILAKU (P5–AA12) ====================
        // Judul: Riwayat Keluarga (lingkari jika ada)
        $sheet->mergeCells('P5:Q6');
        $sheet->setCellValue('P5', "Riwayat Keluarga\n(lingkari jika ada)");
        $sheet->getStyle('P5:Q6')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setWrapText(true);

        // Pilihan riwayat keluarga
        $sheet->setCellValue('R5', 'a. Hipertensi');
        $sheet->setCellValue('S5', 'b. DM');
        $sheet->setCellValue('T5', 'c. Stroke');
        $sheet->setCellValue('U5', 'd. Jantung');
        $sheet->setCellValue('V5', 'f. Kanker');
        $sheet->setCellValue('W5', 'g. Kolesterol Tinggi');

        // Judul: Riwayat Diri Sendiri (lingkari jika ada)
        $sheet->mergeCells('P7:Q8');
        $sheet->setCellValue('P7', "Riwayat Diri Sendiri\n(lingkari jika ada)");
        $sheet->getStyle('P7:Q8')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setWrapText(true);

        // Pilihan riwayat diri sendiri
        $sheet->setCellValue('R7', 'a. Hipertensi');
        $sheet->setCellValue('S7', 'b. DM');
        $sheet->setCellValue('T7', 'c. Stroke');
        $sheet->setCellValue('U7', 'd. Jantung');
        $sheet->setCellValue('V7', 'f. Kanker');
        $sheet->setCellValue('W7', 'g. Kolesterol Tinggi');

        // Judul: Perilaku Berisiko Diri Sendiri (lingkari jika ada)
        $sheet->mergeCells('P9:Q12');
        $sheet->setCellValue('P9', "Perilaku Berisiko Diri Sendiri\n(lingkari jika ada)");
        $sheet->getStyle('P9:Q12')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setWrapText(true);

        // Item perilaku berisiko
        $sheet->setCellValue('R9',  'a. Merokok');
        $sheet->setCellValue('R10', 'b. Konsumsi Tinggi Gula');
        $sheet->setCellValue('R11', 'c. Konsumsi Tinggi Garam');
        $sheet->setCellValue('R12', 'd. Konsumsi Tinggi Lemak');

        // Kolom keterangan Ya/Tidak
        $sheet->setCellValue('X9',  ': Ya/Tidak');
        $sheet->setCellValue('X10', ': Ya/Tidak');
        $sheet->setCellValue('X11', ': Ya/Tidak');
        $sheet->setCellValue('X12', ': Ya/Tidak');

        // Langkah-langkah di AC8–AC12
        $steps = [
            'AC8'  => ['text' => ': Disi langkah 1', 'color' => 'FFFCE2D2'],
            'AC9'  => ['text' => ': Disi langkah 2', 'color' => 'FFFFE79B'],
            'AC10' => ['text' => ': Disi langkah 3', 'color' => 'FFFFFFCC'],
            'AC11' => ['text' => ': Disi langkah 4', 'color' => 'FFD7E1F3'],
            'AC12' => ['text' => ': Disi langkah 5', 'color' => 'FFCCCCFF'],
        ];

        foreach ($steps as $cell => $v) {
            $sheet->setCellValue($cell, $v['text']);
            $sheet->getStyle($cell)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB($v['color']);
        }

        $sheet->getStyle('P5:AA12')->getFont()->setSize(11);
        $sheet->getStyle('P5:AA12')->getAlignment()
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setWrapText(true);

        // ==================== HEADER ATAS ====================
        $sheet->setCellValue('A16', 'Usia Dewasa dan Lansia');
        $sheet->mergeCells('A16:AC16');
        $sheet->getStyle('A16:AC16')->getFont()->setSize(16)->setBold(true);
        $sheet->getStyle('A16:AC16')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A16:AC16')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFD3D3D3');

        // ==================== KOLOM A: WAKTU KE POSYANDU ====================
        $sheet->mergeCells('A18:A20');
        $sheet->setCellValue('A18', "Waktu ke\nPosyandu\n(tanggal/bulan/tahun)");
        $sheet->getStyle('A18')->getFont()->setBold(true);
        $sheet->getStyle('A18')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        // ==================== BARIS 17 — HEADER BESAR ====================
        $sheet->mergeCells('A17:N17');
        $sheet->setCellValue('A17', "Hasil Penimbangan / Pengukuran / Pemeriksaan\n(Jika hasil pemeriksaan Tekanan Darah/Gula Darah tergolong tinggi maka dirujuk ke Pustu/Puskesmas)");
        $sheet->getStyle('A17:N17')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A17:N17')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        $sheet->mergeCells('O17:V17');
        $sheet->setCellValue('O17', "Kuesioner PPOK/PUMA (Skoring) ≥ 40 Tahun dan merokok\n(jika sasaran menjawab dengan score >6 , maka sasaran dirujuk ke Pustu/Puskesmas)");
        $sheet->getStyle('O17:V17')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('O17:V17')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        $sheet->mergeCells('W17:Z17');
        $sheet->setCellValue('W17', 'Hasil Wawancara Faktor Risiko PM');
        $sheet->getStyle('W17:Z17')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('W17:Z17')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('AA17:AA20');
        $sheet->setCellValue('AA17', "Wawancara Usia Dewasa\nyang menggunakan Alat Kontrasepsi\n(Pil/Kondom/Lainnya)\n(Ya/Tidak)");
        $sheet->getStyle('AA17')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        $sheet->mergeCells('AB17:AB20');
        $sheet->setCellValue('AB17', "Edukasi");
        $sheet->getStyle('AB17')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        $sheet->mergeCells('AC17:AC20');
        $sheet->setCellValue('AC17', "Rujuk\nPustu/\nPuskesmas");
        $sheet->getStyle('AC17')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        // ==================== KOLOM PENIMBANGAN & PEMERIKSAAN (B–N) ====================
        $sheet->mergeCells('B18:B20'); $sheet->setCellValue('B18', "Berat\nBadan\n(Kg)");
        $sheet->mergeCells('C18:C20'); $sheet->setCellValue('C18', "Tinggi\nBadan\n(Cm)");
        $sheet->mergeCells('D18:D20'); $sheet->setCellValue('D18', "IMT\nSangat Kurus (SK)/\nKurus (K)/\nNormal (N)/\nGemuk (G)/\nObesitas (O)");
        $sheet->mergeCells('E18:E20'); $sheet->setCellValue('E18', "Lingkar\nPerut\n(Cm)");
        $sheet->mergeCells('F18:F20'); $sheet->setCellValue('F18', "Lingkar\nLengan\nAtas\n(Cm)");
        $sheet->mergeCells('G18:H18'); $sheet->setCellValue('G18', 'Tekanan Darah');
        $sheet->mergeCells('G19:G20'); $sheet->setCellValue('G19', "Sistole/\nDiastole");
        $sheet->mergeCells('H19:H20'); $sheet->setCellValue('H19', "Hasil\n(Rendah/\nNormal/\nTinggi)");
        $sheet->mergeCells('I18:J18'); $sheet->setCellValue('I18', 'Gula Darah');
        $sheet->mergeCells('I19:I20'); $sheet->setCellValue('I19', "Kadar\nGula Darah\nSewaktu\nmg/dL");
        $sheet->mergeCells('J19:J20'); $sheet->setCellValue('J19', "Hasil\n(Rendah/\nNormal/\nTinggi)");
        $sheet->mergeCells('K18:L18'); $sheet->setCellValue('K18', 'Tes Hitung Jari Tangan');
        $sheet->setCellValue('K19', 'Mata Kanan'); $sheet->setCellValue('L19', 'Mata Kiri');
        $sheet->setCellValue('K20', "Normal/\nGangguan"); $sheet->setCellValue('L20', "Normal/\nGangguan");
        $sheet->mergeCells('M18:N18'); $sheet->setCellValue('M18', 'Tes Berbisik');
        $sheet->setCellValue('M19', "Telinga\nKanan"); $sheet->setCellValue('N19', "Telinga\nKiri");
        $sheet->setCellValue('M20', "Normal/\nGangguan"); $sheet->setCellValue('N20', "Normal/\nGangguan");

        // ==================== KUESIONER PPOK/PUMA (O–V) ====================
        $sheet->setCellValue('O18', "Jenis\nKelamin");
        $sheet->setCellValue('P18', "Usia");
        $sheet->setCellValue('Q18', "Merokok");
        $sheet->mergeCells('R18:R20');
        $sheet->setCellValue('R18', "Apakah Anda sering merasa\nnapas pendek saat berjalan\ncepat di jalan datar atau\nsedikit menanjak?\n\n(Tidak = 0 | Ya = 1)");
        $sheet->mergeCells('S18:S20');
        $sheet->setCellValue('S18', "Apakah Anda sering\nmempunyai dahak dari paru\natau sulit mengeluarkan\ndahak saat tidak flu?\n\n(Tidak = 0 | Ya = 1)");
        $sheet->mergeCells('T18:T20');
        $sheet->setCellValue('T18', "Apakah Anda biasanya\nbatuk saat tidak sedang\nmenderita flu?\n\n(Tidak = 0 | Ya = 1)");
        $sheet->mergeCells('U18:U20');
        $sheet->setCellValue('U18', "Pernahkah dokter/tenaga\nkesehatan meminta Anda\nmeniup alat spirometri\natau peakflow meter?\n\n(Tidak = 0 | Ya = 1)");
        $sheet->mergeCells('V18:V20');
        $sheet->setCellValue('V18', "Skor\nPUMA");
        $sheet->mergeCells('O19:O20'); $sheet->setCellValue('O19', "Pr = 0\nLk = 1");
        $sheet->mergeCells('P19:P20'); $sheet->setCellValue('P19', "40-49 = 0\n50-59 = 1\n≥ 60 = 2");
        $sheet->mergeCells('Q19:Q20'); $sheet->setCellValue('Q19', "Tidak = 0\n<20 Bks/Th = 0\n20-39 Bks/Th = 1\n≥40 Bks/Th = 2");
        $sheet->setCellValue('R20', "Tidak = 0\nYa = 5");
        $sheet->setCellValue('S20', "Tidak = 0\nYa = 4");
        $sheet->setCellValue('T20', "Tidak = 0\nYa = 4");
        $sheet->setCellValue('U20', "Tidak = 0\nYa = 5");
        $sheet->mergeCells('V19:V20'); $sheet->setCellValue('V19', "< 6\n≥ 6");

        // ==================== SKRINING TBC (W–Z) ====================
        $sheet->mergeCells('W18:Z18');
        $sheet->setCellValue('W18', 'Skrining Gejala TBC (jika 2 gejala terpenuhi maka dirujuk ke Puskesmas)');
        $sheet->getStyle('W18:Z18')->getFont()->setBold(true);
        $sheet->getStyle('W18:Z18')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->mergeCells('W19:W20'); $sheet->setCellValue('W19', "Batuk\nterus\nmenerus\n(Ya/Tidak)");
        $sheet->mergeCells('X19:X20'); $sheet->setCellValue('X19', "Demam\nlebih dari\n2 minggu\n(Ya/Tidak)");
        $sheet->mergeCells('Y19:Y20'); $sheet->setCellValue('Y19', "BB tidak\nnaik atau\nturun dalam\n2 bulan\n(Ya/Tidak)");
        $sheet->mergeCells('Z19:Z20'); $sheet->setCellValue('Z19', "Kontak erat\ndengan\nPasien TBC\n(Ya/Tidak)");

        // ==================== ISI IDENTITAS (MULAI BARIS 5) ====================
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
            $sheet->setCellValue("C{$row}", $value ?? '-');
        }

        // Jenis kelamin (RichText Laki-laki / Perempuan)
        $row = 5;
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
        $sheet->setCellValue("D{$row}", $richText);

        // Umur (tahun) di D6
        $row = 6;
        if ($warga->tanggal_lahir) {
            $lahir = Carbon::parse($warga->tanggal_lahir);
            $diff  = $lahir->diff(now());
            $tahun = $diff->y;

        } else {
            $tahun = 0;
        }
        $sheet->setCellValue("D{$row}", '( ' . $tahun . ' Tahun )');

        // ==================== ISI DATA (BARIS 21, LOOP SEMUA RIWAYAT) ====================
        $row = 21;

        foreach ($periksas as $periksa) {
            // Tanggal
            $sheet->setCellValue("A{$row}", Carbon::parse($periksa->tanggal_periksa)->translatedFormat('d F Y') ?? '-');

            // IMT & kategori
            $imt = ($periksa->tinggi_badan > 0)
                ? round($periksa->berat_badan / (($periksa->tinggi_badan / 100) ** 2), 2)
                : 0;

            $kategori = $imt < 17   ? 'SK'
                      : ($imt < 18.5 ? 'K'
                      : ($imt < 25   ? 'N'
                      : ($imt < 30   ? 'G' : 'O')));


            $sheet->setCellValue("B{$row}", $periksa->berat_badan ?? '');
            $sheet->setCellValue("C{$row}", $periksa->tinggi_badan ?? '');
            $sheet->setCellValue("D{$row}", $imt);
            $sheet->setCellValue("E{$row}", $periksa->lingkar_perut ?? '');
            $sheet->setCellValue("F{$row}", $periksa->lingkar_lengan_atas ?? '');
            $sheet->setCellValue("G{$row}", ($periksa->sistole ?? '').'/'.($periksa->diastole ?? ''));
            $sheet->setCellValue(
                "H{$row}",
                ($periksa->sistole >= 140 || $periksa->diastole >= 90) ? 'Tinggi' : 'Normal'
            );
            $sheet->setCellValue("I{$row}", $periksa->gula_darah ?? '');
            $sheet->setCellValue(
                "J{$row}",
                $periksa->gula_darah > 200 ? 'Tinggi'
                    : ($periksa->gula_darah < 70 ? 'Rendah' : 'Normal')
            );

            $sheet->setCellValue("K{$row}", $periksa->mata_kanan === 'G' ? 'Gangguan' : 'Normal');
            $sheet->setCellValue("L{$row}", $periksa->mata_kiri === 'G' ? 'Gangguan' : 'Normal');
            $sheet->setCellValue("M{$row}", $periksa->telinga_kanan === 'G' ? 'Gangguan' : 'Normal');
            $sheet->setCellValue("N{$row}", $periksa->telinga_kiri === 'G' ? 'Gangguan' : 'Normal');

            // PUMA
            $jkSkor   = ($warga->jenis_kelamin === 'Laki-laki' || $warga->jenis_kelamin === 'L') ? 1 : 0;
            $umur     = $warga->tanggal_lahir ? now()->diffInYears($warga->tanggal_lahir) : 0;
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

            $sheet->setCellValue("O{$row}", $jkSkor);
            $sheet->setCellValue("P{$row}", $usiaSkor);
            $sheet->setCellValue("Q{$row}", $merokokSkor);
            $sheet->setCellValue("R{$row}", $q1 ? 'Ya' : 'Tidak');
            $sheet->setCellValue("S{$row}", $q2 ? 'Ya' : 'Tidak');
            $sheet->setCellValue("T{$row}", $q3 ? 'Ya' : 'Tidak');
            $sheet->setCellValue("U{$row}", $q4 ? 'Ya' : 'Tidak');
            $sheet->setCellValue("V{$row}", $totalPuma >= 6 ? '≥ 6' : $totalPuma);

            // TBC
            $sheet->setCellValue("W{$row}", $periksa->tbc_batuk       ?? 'Tidak');
            $sheet->setCellValue("X{$row}", $periksa->tbc_demam       ?? 'Tidak');
            $sheet->setCellValue("Y{$row}", $periksa->tbc_bb_turun    ?? 'Tidak');
            $sheet->setCellValue("Z{$row}", $periksa->tbc_kontak_erat ?? 'Tidak');
            $sheet->setCellValue("AA{$row}", $periksa->kontrasepsi ?? '-');
            $sheet->setCellValue("AB{$row}", $periksa->edukasi     ?? '-');

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
                $sheet->setCellValue("AC{$row}", 'YA (' . implode(', ', $rujuk) . ')');
                $sheet->getStyle("AC{$row}")
                    ->getFont()->setBold(true)->getColor()->setARGB('FFFF0000');
            } else {
                // 0 = Tidak, 1 = Ya
                $sheet->setCellValue(
                    "AC{$row}",
                    ($periksa->rujuk_puskesmas == 1 ? 'Ya' : 'Tidak')
                );
            }

            $row++; // pindah ke baris riwayat berikutnya
        }

        $lastRow = $row - 1;

        // ==================== WARNA BACKGROUND ====================
        $sheet->getStyle('A18:A20')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFCE2D2');
        $sheet->getStyle('B18:B20')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFE79B');
        $sheet->getStyle('D18:D20')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFFCC');
        $sheet->getStyle('E18:E20')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFE79B');
        $sheet->getStyle('G18:G20')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFE79B');
        $sheet->getStyle('H18:H20')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFE79B');
        $sheet->getStyle('I18:AA20')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD7E1F3');
        $sheet->getStyle('AA17:AA20')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD7E1F3');
        $sheet->getStyle('AB17:AB20')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFCCCCFF');
        $sheet->getStyle('AC17:AC20')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD7E1F3');
        $sheet->getStyle('F17:F20')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFE79B');
        $sheet->getStyle('C17:C20')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFE79B');
        $sheet->getStyle('B17:H20')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD8D8D8');
        $sheet->getStyle('I18:N20')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD7E1F3');

        $sheet->getStyle('O17:V20')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD7E1F3');
        $sheet->getStyle('W17:Z20')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD7E1F3');

        // ==================== STYLING UMUM ====================
        $sheet->getStyle('A18:AC20')->getFont()->setBold(true);
        $sheet->getStyle("A16:AC{$lastRow}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A18:AC{$lastRow}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        foreach (array_merge(range('A', 'Z'), ['AA', 'AB', 'AC']) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
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


    // SEMUA DATA WARGA DEWASA & LANSIA BESERTA PEMERIKSAAN
    // 1 FILE BANYAK SHEET PER WARGA
    // protected function buildKartuSheet(Worksheet $sheet, Warga $warga): void
    // {
    //     $periksas = $warga->pemeriksaanDewasaLansia; // Collection

    //     if ($periksas->isEmpty()) {
    //         $sheet->setCellValue('A1', 'Belum ada data pemeriksaan');
    //         return;
    //     }

    //     // ==================== JUDUL ====================
    //     $sheet->setCellValue('A2', 'KARTU BANTU PEMERIKSAAN USIA DEWASA DAN LANSIA (>19 Tahun)');
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

    //     // ==================== RIWAYAT KELUARGA / DIRI / PERILAKU (P5–AA12) ====================

    //     $sheet->mergeCells('P5:Q6');
    //     $sheet->setCellValue('P5', "Riwayat Keluarga\n(lingkari jika ada)");
    //     $sheet->getStyle('P5:Q6')->getAlignment()
    //         ->setHorizontal(Alignment::HORIZONTAL_LEFT)
    //         ->setVertical(Alignment::VERTICAL_TOP)
    //         ->setWrapText(true);

    //     $sheet->setCellValue('R5', 'a. Hipertensi');
    //     $sheet->setCellValue('S5', 'b. DM');
    //     $sheet->setCellValue('T5', 'c. Stroke');
    //     $sheet->setCellValue('U5', 'd. Jantung');
    //     $sheet->setCellValue('V5', 'f. Kanker');
    //     $sheet->setCellValue('W5', 'g. Kolesterol Tinggi');

    //     $sheet->mergeCells('P7:Q8');
    //     $sheet->setCellValue('P7', "Riwayat Diri Sendiri\n(lingkari jika ada)");
    //     $sheet->getStyle('P7:Q8')->getAlignment()
    //         ->setHorizontal(Alignment::HORIZONTAL_LEFT)
    //         ->setVertical(Alignment::VERTICAL_TOP)
    //         ->setWrapText(true);

    //     $sheet->setCellValue('R7', 'a. Hipertensi');
    //     $sheet->setCellValue('S7', 'b. DM');
    //     $sheet->setCellValue('T7', 'c. Stroke');
    //     $sheet->setCellValue('U7', 'd. Jantung');
    //     $sheet->setCellValue('V7', 'f. Kanker');
    //     $sheet->setCellValue('W7', 'g. Kolesterol Tinggi');

    //     $sheet->mergeCells('P9:Q12');
    //     $sheet->setCellValue('P9', "Perilaku Berisiko Diri Sendiri\n(lingkari jika ada)");
    //     $sheet->getStyle('P9:Q12')->getAlignment()
    //         ->setHorizontal(Alignment::HORIZONTAL_LEFT)
    //         ->setVertical(Alignment::VERTICAL_TOP)
    //         ->setWrapText(true);

    //     $sheet->setCellValue('R9',  'a. Merokok');
    //     $sheet->setCellValue('R10', 'b. Konsumsi Tinggi Gula');
    //     $sheet->setCellValue('R11', 'c. Konsumsi Tinggi Garam');
    //     $sheet->setCellValue('R12', 'd. Konsumsi Tinggi Lemak');

    //     $sheet->setCellValue('X9',  ': Ya/Tidak');
    //     $sheet->setCellValue('X10', ': Ya/Tidak');
    //     $sheet->setCellValue('X11', ': Ya/Tidak');
    //     $sheet->setCellValue('X12', ': Ya/Tidak');

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

    //     // ==================== HEADER ATAS ====================
    //     $sheet->setCellValue('A16', 'Usia Dewasa dan Lansia');
    //     $sheet->mergeCells('A16:AC16');
    //     $sheet->getStyle('A16:AC16')->getFont()->setSize(16)->setBold(true);
    //     $sheet->getStyle('A16:AC16')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    //     $sheet->getStyle('A16:AC16')->getFill()
    //         ->setFillType(Fill::FILL_SOLID)
    //         ->getStartColor()->setARGB('FFD3D3D3');

    //     // ==================== KOLOM A: WAKTU KE POSYANDU ====================
    //     $sheet->mergeCells('A18:A20');
    //     $sheet->setCellValue('A18', "Waktu ke\nPosyandu\n(tanggal/bulan/tahun)");
    //     $sheet->getStyle('A18')->getFont()->setBold(true);
    //     $sheet->getStyle('A18')->getAlignment()
    //         ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    //         ->setVertical(Alignment::VERTICAL_CENTER)
    //         ->setWrapText(true);

    //     // ==================== BARIS 17 — HEADER BESAR ====================
    //     $sheet->mergeCells('A17:N17');
    //     $sheet->setCellValue('A17', "Hasil Penimbangan / Pengukuran / Pemeriksaan\n(Jika hasil pemeriksaan Tekanan Darah/Gula Darah tergolong tinggi maka dirujuk ke Pustu/Puskesmas)");
    //     $sheet->getStyle('A17:N17')->getFont()->setBold(true)->setSize(11);
    //     $sheet->getStyle('A17:N17')->getAlignment()
    //         ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    //         ->setVertical(Alignment::VERTICAL_CENTER)
    //         ->setWrapText(true);

    //     $sheet->mergeCells('O17:V17');
    //     $sheet->setCellValue('O17', "Kuesioner PPOK/PUMA (Skoring) ≥ 40 Tahun dan merokok\n(jika sasaran menjawab dengan score >6 , maka sasaran dirujuk ke Pustu/Puskesmas)");
    //     $sheet->getStyle('O17:V17')->getFont()->setBold(true)->setSize(11);
    //     $sheet->getStyle('O17:V17')->getAlignment()
    //         ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    //         ->setVertical(Alignment::VERTICAL_CENTER)
    //         ->setWrapText(true);

    //     $sheet->mergeCells('W17:Z17');
    //     $sheet->setCellValue('W17', 'Hasil Wawancara Faktor Risiko PM');
    //     $sheet->getStyle('W17:Z17')->getFont()->setBold(true)->setSize(12);
    //     $sheet->getStyle('W17:Z17')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    //     $sheet->mergeCells('AA17:AA20');
    //     $sheet->setCellValue('AA17', "Wawancara Usia Dewasa\nyang menggunakan Alat Kontrasepsi\n(Pil/Kondom/Lainnya)\n(Ya/Tidak)");
    //     $sheet->getStyle('AA17')->getAlignment()
    //         ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    //         ->setVertical(Alignment::VERTICAL_CENTER)
    //         ->setWrapText(true);

    //     $sheet->mergeCells('AB17:AB20');
    //     $sheet->setCellValue('AB17', "Edukasi");
    //     $sheet->getStyle('AB17')->getAlignment()
    //         ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    //         ->setVertical(Alignment::VERTICAL_CENTER)
    //         ->setWrapText(true);

    //     $sheet->mergeCells('AC17:AC20');
    //     $sheet->setCellValue('AC17', "Rujuk\nPustu/\nPuskesmas");
    //     $sheet->getStyle('AC17')->getAlignment()
    //         ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    //         ->setVertical(Alignment::VERTICAL_CENTER)
    //         ->setWrapText(true);

    //     // ==================== KOLOM PENIMBANGAN & PEMERIKSAAN (B–N) ====================
    //     $sheet->mergeCells('B18:B20'); $sheet->setCellValue('B18', "Berat\nBadan\n(Kg)");
    //     $sheet->mergeCells('C18:C20'); $sheet->setCellValue('C18', "Tinggi\nBadan\n(Cm)");
    //     $sheet->mergeCells('D18:D20'); $sheet->setCellValue('D18', "IMT\nSangat Kurus (SK)/\nKurus (K)/\nNormal (N)/\nGemuk (G)/\nObesitas (O)");
    //     $sheet->mergeCells('E18:E20'); $sheet->setCellValue('E18', "Lingkar\nPerut\n(Cm)");
    //     $sheet->mergeCells('F18:F20'); $sheet->setCellValue('F18', "Lingkar\nLengan\nAtas\n(Cm)");
    //     $sheet->mergeCells('G18:H18'); $sheet->setCellValue('G18', 'Tekanan Darah');
    //     $sheet->mergeCells('G19:G20'); $sheet->setCellValue('G19', "Sistole/\nDiastole");
    //     $sheet->mergeCells('H19:H20'); $sheet->setCellValue('H19', "Hasil\n(Rendah/\nNormal/\nTinggi)");
    //     $sheet->mergeCells('I18:J18'); $sheet->setCellValue('I18', 'Gula Darah');
    //     $sheet->mergeCells('I19:I20'); $sheet->setCellValue('I19', "Kadar\nGula Darah\nSewaktu\nmg/dL");
    //     $sheet->mergeCells('J19:J20'); $sheet->setCellValue('J19', "Hasil\n(Rendah/\nNormal/\nTinggi)");
    //     $sheet->mergeCells('K18:L18'); $sheet->setCellValue('K18', 'Tes Hitung Jari Tangan');
    //     $sheet->setCellValue('K19', 'Mata Kanan'); $sheet->setCellValue('L19', 'Mata Kiri');
    //     $sheet->setCellValue('K20', "Normal/\nGangguan"); $sheet->setCellValue('L20', "Normal/\nGangguan");
    //     $sheet->mergeCells('M18:N18'); $sheet->setCellValue('M18', 'Tes Berbisik');
    //     $sheet->setCellValue('M19', "Telinga\nKanan"); $sheet->setCellValue('N19', "Telinga\nKiri");
    //     $sheet->setCellValue('M20', "Normal/\nGangguan"); $sheet->setCellValue('N20', "Normal/\nGangguan");

    //     // ==================== KUESIONER PPOK/PUMA (O–V) ====================
    //     $sheet->setCellValue('O18', "Jenis\nKelamin");
    //     $sheet->setCellValue('P18', "Usia");
    //     $sheet->setCellValue('Q18', "Merokok");
    //     $sheet->mergeCells('R18:R20');
    //     $sheet->setCellValue('R18', "Apakah Anda sering merasa\nnapas pendek saat berjalan\ncepat di jalan datar atau\nsedikit menanjak?\n\n(Tidak = 0 | Ya = 5)");
    //     $sheet->mergeCells('S18:S20');
    //     $sheet->setCellValue('S18', "Apakah Anda sering\nmempunyai dahak dari paru\natau sulit mengeluarkan\ndahak saat tidak flu?\n\n(Tidak = 0 | Ya = 4)");
    //     $sheet->mergeCells('T18:T20');
    //     $sheet->setCellValue('T18', "Apakah Anda biasanya\nbatuk saat tidak sedang\nmenderita flu?\n\n(Tidak = 0 | Ya = 4)");
    //     $sheet->mergeCells('U18:U20');
    //     $sheet->setCellValue('U18', "Pernahkah dokter/tenaga\nkesehatan meminta Anda\nmeniup alat spirometri\natau peakflow meter?\n\n(Tidak = 0 | Ya = 5)");
    //     $sheet->mergeCells('V18:V20');
    //     $sheet->setCellValue('V18', "Skor\nPUMA");
    //     $sheet->mergeCells('O19:O20'); $sheet->setCellValue('O19', "Pr = 0\nLk = 1");
    //     $sheet->mergeCells('P19:P20'); $sheet->setCellValue('P19', "40-49 = 0\n50-59 = 1\n≥ 60 = 2");
    //     $sheet->mergeCells('Q19:Q20'); $sheet->setCellValue('Q19', "Tidak = 0\n<20 Bks/Th = 0\n20-39 Bks/Th = 1\n≥40 Bks/Th = 2");
    //     $sheet->setCellValue('R20', "Tidak = 0\nYa = 5");
    //     $sheet->setCellValue('S20', "Tidak = 0\nYa = 4");
    //     $sheet->setCellValue('T20', "Tidak = 0\nYa = 4");
    //     $sheet->setCellValue('U20', "Tidak = 0\nYa = 5");
    //     $sheet->mergeCells('V19:V20'); $sheet->setCellValue('V19', "< 6\n≥ 6");

    //     // ==================== SKRINING TBC (W–Z) ====================
    //     $sheet->mergeCells('W18:Z18');
    //     $sheet->setCellValue('W18', 'Skrining Gejala TBC (jika 2 gejala terpenuhi maka dirujuk ke Puskesmas)');
    //     $sheet->getStyle('W18:Z18')->getFont()->setBold(true);
    //     $sheet->getStyle('W18:Z18')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    //     $sheet->mergeCells('W19:W20'); $sheet->setCellValue('W19', "Batuk\nterus\nmenerus\n(Ya/Tidak)");
    //     $sheet->mergeCells('X19:X20'); $sheet->setCellValue('X19', "Demam\nlebih dari\n2 minggu\n(Ya/Tidak)");
    //     $sheet->mergeCells('Y19:Y20'); $sheet->setCellValue('Y19', "BB tidak\nnaik atau\nturun dalam\n2 bulan\n(Ya/Tidak)");
    //     $sheet->mergeCells('Z19:Z20'); $sheet->setCellValue('Z19', "Kontak erat\ndengan\nPasien TBC\n(Ya/Tidak)");

    //     // ==================== ISI IDENTITAS (MULAI BARIS 5) ====================
    //     $dataIdentitas = [
    //         5  => $warga->nama,
    //         6  => $warga->nik,
    //         7  => $warga->tanggal_lahir,
    //         8  => $warga->alamat,
    //         9  => $warga->no_hp,
    //         10 => $warga->status_nikah,
    //         11 => $warga->pekerjaan,
    //         12 => sprintf('%s/%s/%s', $warga->dusun ?? '-', $warga->rt ?? '-', $warga->rw ?? '-'),
    //         13 => $warga->kecamatan,
    //         14 => $warga->desa
    //     ];

    //     foreach ($dataIdentitas as $row => $value) {
    //         $sheet->setCellValue("C{$row}", $value ?? '-');
    //     }

    //     // Jenis kelamin (RichText)
    //     $row = 5;
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
    //     $sheet->setCellValue("D{$row}", $richText);

    //     // Umur (tahun) di D6
    //     $row = 6;
    //     if ($warga->tanggal_lahir) {
    //         $lahir = Carbon::parse($warga->tanggal_lahir);
    //         $tahun = $lahir->diffInYears(now());
    //     } else {
    //         $tahun = 0;
    //     }
    //     $sheet->setCellValue("D{$row}", '( ' . $tahun . ' Tahun )');

    //     // ==================== ISI DATA RIWAYAT (BARIS 21, LOOP) ====================
    //     $row = 21;

    //     foreach ($periksas as $periksa) {
    //         $sheet->setCellValue("A{$row}", $periksa->tanggal_periksa ?? '-');

    //         $imt = ($periksa->tinggi_badan > 0)
    //             ? round($periksa->berat_badan / (($periksa->tinggi_badan / 100) ** 2), 2)
    //             : 0;

    //         $kategori = $imt < 17   ? 'SK'
    //                   : ($imt < 18.5 ? 'K'
    //                   : ($imt < 25   ? 'N'
    //                   : ($imt < 30   ? 'G' : 'O')));

    //         $sheet->setCellValue("B{$row}", $periksa->berat_badan ?? '');
    //         $sheet->setCellValue("C{$row}", $periksa->tinggi_badan ?? '');
    //         $sheet->setCellValue("D{$row}", $kategori);
    //         $sheet->setCellValue("E{$row}", $periksa->lingkar_perut ?? '');
    //         $sheet->setCellValue("F{$row}", $periksa->lingkar_lengan_atas ?? '');
    //         $sheet->setCellValue("G{$row}", ($periksa->sistole ?? '').'/'.($periksa->diastole ?? ''));
    //         $sheet->setCellValue(
    //             "H{$row}",
    //             ($periksa->sistole >= 140 || $periksa->diastole >= 90) ? 'Tinggi' : 'Normal'
    //         );
    //         $sheet->setCellValue("I{$row}", $periksa->gula_darah ?? '');
    //         $sheet->setCellValue(
    //             "J{$row}",
    //             $periksa->gula_darah > 200 ? 'Tinggi'
    //                 : ($periksa->gula_darah < 70 ? 'Rendah' : 'Normal')
    //         );
    //         $sheet->setCellValue("K{$row}", $periksa->mata_kanan ?? 'Normal');
    //         $sheet->setCellValue("L{$row}", $periksa->mata_kiri ?? 'Normal');
    //         $sheet->setCellValue("M{$row}", $periksa->telinga_kanan ?? 'Normal');
    //         $sheet->setCellValue("N{$row}", $periksa->telinga_kiri ?? 'Normal');

    //         $jkSkor   = ($warga->jenis_kelamin === 'Laki-laki' || $warga->jenis_kelamin === 'L') ? 1 : 0;
    //         $umur     = $warga->tanggal_lahir ? now()->diffInYears($warga->tanggal_lahir) : 0;
    //         $usiaSkor = $umur >= 60 ? 2 : ($umur >= 50 ? 1 : 0);

    //         $merokokSkor = match ($periksa->merokok ?? 0) {
    //             0 => 0,
    //             1 => 0,
    //             2 => 1,
    //             3 => 2,
    //             default => 0,
    //         };

    //         $q1 = ($periksa->puma_napas_pendek ?? 'Tidak') === 'Ya' ? 5 : 0;
    //         $q2 = ($periksa->puma_dahak ?? 'Tidak')        === 'Ya' ? 4 : 0;
    //         $q3 = ($periksa->puma_batuk ?? 'Tidak')        === 'Ya' ? 4 : 0;
    //         $q4 = ($periksa->puma_tes_paru ?? 'Tidak')     === 'Ya' ? 5 : 0;

    //         $totalPuma = $jkSkor + $usiaSkor + $merokokSkor + $q1 + $q2 + $q3 + $q4;

    //         $sheet->setCellValue("O{$row}", $jkSkor);
    //         $sheet->setCellValue("P{$row}", $usiaSkor);
    //         $sheet->setCellValue("Q{$row}", $merokokSkor);
    //         $sheet->setCellValue("R{$row}", $q1 ? 'Ya' : 'Tidak');
    //         $sheet->setCellValue("S{$row}", $q2 ? 'Ya' : 'Tidak');
    //         $sheet->setCellValue("T{$row}", $q3 ? 'Ya' : 'Tidak');
    //         $sheet->setCellValue("U{$row}", $q4 ? 'Ya' : 'Tidak');
    //         $sheet->setCellValue("V{$row}", $totalPuma >= 6 ? '≥ 6' : $totalPuma);

    //         $sheet->setCellValue("W{$row}", $periksa->tbc_batuk       ?? 'Tidak');
    //         $sheet->setCellValue("X{$row}", $periksa->tbc_demam       ?? 'Tidak');
    //         $sheet->setCellValue("Y{$row}", $periksa->tbc_bb_turun    ?? 'Tidak');
    //         $sheet->setCellValue("Z{$row}", $periksa->tbc_kontak_erat ?? 'Tidak');
    //         $sheet->setCellValue("AA{$row}", $periksa->kontrasepsi ?? '-');
    //         $sheet->setCellValue("AB{$row}", $periksa->edukasi     ?? '-');

    //         $rujuk = [];
    //         if (($periksa->sistole >= 140 || $periksa->diastole >= 90) || ($periksa->gula_darah > 200)) {
    //             $rujuk[] = 'TD/Gula Darah Tinggi';
    //         }
    //         if ($totalPuma > 6) {
    //             $rujuk[] = 'Skor PUMA >6';
    //         }

    //         $gejalaTBC = collect([
    //             $periksa->tbc_batuk,
    //             $periksa->tbc_demam,
    //             $periksa->tbc_bb_turun,
    //             $periksa->tbc_kontak_erat,
    //         ])->filter(fn ($v) => $v === 'Ya')->count();

    //         if ($gejalaTBC >= 2) {
    //             $rujuk[] = 'Suspek TBC';
    //         }

    //         if (!empty($rujuk)) {
    //             $sheet->setCellValue("AC{$row}", 'YA (' . implode(', ', $rujuk) . ')');
    //             $sheet->getStyle("AC{$row}")
    //                 ->getFont()->setBold(true)->getColor()->setARGB('FFFF0000');
    //         } else {
    //             $sheet->setCellValue(
    //                 "AC{$row}",
    //                 ($periksa->rujuk_puskesmas == 1 ? 'Ya' : 'Tidak')
    //             );
    //         }

    //         $row++;
    //     }

    //     $lastRow = $row - 1;

    //     // ==================== WARNA BACKGROUND ====================
    //     $sheet->getStyle('A18:A20')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFCE2D2');
    //     $sheet->getStyle('B18:B20')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFE79B');
    //     $sheet->getStyle('D18:D20')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFFCC');
    //     $sheet->getStyle('E18:E20')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFE79B');
    //     $sheet->getStyle('G18:G20')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFE79B');
    //     $sheet->getStyle('H18:H20')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFE79B');
    //     $sheet->getStyle('I18:AA20')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD7E1F3');
    //     $sheet->getStyle('AA17:AA20')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD7E1F3');
    //     $sheet->getStyle('AB17:AB20')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFCCCCFF');
    //     $sheet->getStyle('AC17:AC20')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD7E1F3');
    //     $sheet->getStyle('F17:F20')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFE79B');
    //     $sheet->getStyle('C17:C20')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFE79B');
    //     $sheet->getStyle('B17:H20')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD8D8D8');
    //     $sheet->getStyle('I18:N20')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD7E1F3');

    //     $sheet->getStyle('O17:V20')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD7E1F3');
    //     $sheet->getStyle('W17:Z20')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD7E1F3');

    //     // ==================== STYLING UMUM ====================
    //     $sheet->getStyle('A18:AC20')->getFont()->setBold(true);
    //     $sheet->getStyle("A16:AC{$lastRow}")->getBorders()->getAllBorders()
    //         ->setBorderStyle(Border::BORDER_THIN);
    //     $sheet->getStyle("A18:AC{$lastRow}")->getAlignment()
    //         ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    //         ->setVertical(Alignment::VERTICAL_CENTER)
    //         ->setWrapText(true);

    //     foreach (array_merge(range('A', 'Z'), ['AA', 'AB', 'AC']) as $col) {
    //         $sheet->getColumnDimension($col)->setAutoSize(true);
    //     }

    //     $sheet->getRowDimension(17)->setRowHeight(45);
    //     $sheet->getRowDimension(18)->setRowHeight(75);
    //     $sheet->getRowDimension(19)->setRowHeight(50);
    //     $sheet->getRowDimension(20)->setRowHeight(50);
    // }

    // /**
    //  * Export 1 warga = 1 file Excel
    //  */
    // public function exportKartuExcel(Warga $warga)
    // {
    //     $spreadsheet = new Spreadsheet();
    //     $sheet = $spreadsheet->getActiveSheet();
    //     $sheet->setTitle(mb_substr($warga->nama ?? 'Kartu', 0, 31));

    //     $this->buildKartuSheet($sheet, $warga);

    //     $filename = "Kartu_Pemeriksaan_Dewasa_Lansia_{$warga->nik}.xlsx";
    //     header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    //     header('Content-Disposition: attachment; filename="' . $filename . '"');
    //     header('Cache-Control: max-age=0');

    //     $writer = new Xlsx($spreadsheet);
    //     $writer->save('php://output');
    //     exit;
    // }

    // /**
    //  * Export banyak warga = 1 file Excel (1 sheet per warga)
    //  */
    // public function exportKartuExcelSemua()
    // {
    //     $wargas = Warga::with('pemeriksaanDewasaLansia')
    //         ->whereHas('pemeriksaanDewasaLansia')
    //         ->get();

    //     if ($wargas->isEmpty()) {
    //         abort(404, 'Belum ada data pemeriksaan untuk warga mana pun');
    //     }

    //     $spreadsheet = new Spreadsheet();

    //     $index = 0;
    //     foreach ($wargas as $warga) {
    //         if ($index === 0) {
    //             $sheet = $spreadsheet->getActiveSheet();
    //         } else {
    //             $sheet = $spreadsheet->createSheet($index);
    //         }

    //         $title = $warga->nama ?: 'Warga ' . $warga->id;
    //         $sheet->setTitle(mb_substr($title, 0, 31));

    //         $this->buildKartuSheet($sheet, $warga);

    //         $index++;
    //     }

    //     $filename = "Kartu_Pemeriksaan_Dewasa_Lansia_SEMUA.xlsx";
    //     header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    //     header('Content-Disposition: attachment; filename="' . $filename . '"');
    //     header('Cache-Control: max-age=0');

    //     $writer = new Xlsx($spreadsheet);
    //     $writer->save('php://output');
    //     exit;
    // }


    // SEMUA DATA WARGA DEWASA & LANSIA BESERTA PEMERIKSAAN
    // ! HALMAN BANYAK KARTU
    // protected function buildKartuSheetOffset(Worksheet $sheet, Warga $warga, int $offsetRow = 0): int
    // {
    //     // helper untuk geser baris
    //     $r = fn(int $n) => $n + $offsetRow;

    //     // ambil semua riwayat
    //     $periksas = $warga->pemeriksaanDewasaLansiaAll;

    //     if ($periksas->isEmpty()) {
    //         $sheet->setCellValue('A' . $r(2), 'Belum ada data pemeriksaan');
    //         return $r(5);
    //     }

    //     // ==================== JUDUL ====================
    //     $sheet->setCellValue('A' . $r(2), 'KARTU BANTU PEMERIKSAAN USIA DEWASA DAN LANSIA (>19 Tahun)');
    //     $sheet->mergeCells('A' . $r(2) . ':AC' . $r(2));
    //     $sheet->getStyle('A' . $r(2) . ':AC' . $r(2))->getFont()->setSize(16)->setBold(true);
    //     $sheet->getStyle('A' . $r(2) . ':AC' . $r(2))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    //     $sheet->setCellValue('A' . $r(3), 'POSYANDU TAMAN CIPULIR ESTATE');
    //     $sheet->mergeCells('A' . $r(3) . ':AC' . $r(3));
    //     $sheet->getStyle('A' . $r(3) . ':AC' . $r(3))->getFont()->setSize(16)->setBold(true);
    //     $sheet->getStyle('A' . $r(3) . ':AC' . $r(3))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    //     // ==================== IDENTITAS (LABEL) ====================
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
    //         $col = preg_replace('/[0-9]/', '', $cell);           // ambil huruf
    //         $row = (int) preg_replace('/\D/', '', $cell);        // ambil angka
    //         $row = $r($row);
    //         $addr = $col . $row;

    //         $sheet->setCellValue($addr, $text);
    //         $style = $sheet->getStyle($addr);
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
    //         $col = preg_replace('/[0-9]/', '', $cell);
    //         $row = (int) preg_replace('/\D/', '', $cell);
    //         $row = $r($row);
    //         $addr = $col . $row;

    //         $sheet->setCellValue($addr, $text);
    //         $style = $sheet->getStyle($addr);
    //         $style->getFont()->setSize(12)->setBold(false);
    //         $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    //     }

    //     // ==================== ISI IDENTITAS ====================
    //     $dataIdentitas = [
    //         5  => $warga->nama,
    //         6  => $warga->nik,
    //         7  => $warga->tanggal_lahir,
    //         8  => $warga->alamat,
    //         9  => $warga->no_hp,
    //         10 => $warga->status_nikah,
    //         11 => $warga->pekerjaan,
    //         12 => sprintf('%s/%s/%s', $warga->dusun ?? '-', $warga->rt ?? '-', $warga->rw ?? '-'),
    //         13 => $warga->kecamatan,
    //         14 => $warga->desa
    //     ];

    //     foreach ($dataIdentitas as $row => $value) {
    //         $sheet->setCellValue('C' . $r($row), $value ?? '-');
    //     }

    //     // ==================== JENIS KELAMIN (RichText) ====================
    //     $jkRow = $r(5);
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
    //     $sheet->setCellValue('D' . $jkRow, $richText);

    //     // ==================== UMUR ====================
    //     $umurRow = $r(6);
    //     if ($warga->tanggal_lahir) {
    //         $lahir = Carbon::parse($warga->tanggal_lahir);
    //         $tahun = $lahir->diffInYears(now());
    //     } else {
    //         $tahun = 0;
    //     }
    //     $sheet->setCellValue('D' . $umurRow, '( ' . $tahun . ' Tahun )');

    //     // ==================== RIWAYAT KELUARGA / DIRI / PERILAKU ====================
    //     $sheet->mergeCells('P' . $r(5) . ':Q' . $r(6));
    //     $sheet->setCellValue('P' . $r(5), "Riwayat Keluarga\n(lingkari jika ada)");
    //     $sheet->getStyle('P' . $r(5) . ':Q' . $r(6))->getAlignment()
    //         ->setHorizontal(Alignment::HORIZONTAL_LEFT)
    //         ->setVertical(Alignment::VERTICAL_TOP)
    //         ->setWrapText(true);

    //     $sheet->setCellValue('R' . $r(5), 'a. Hipertensi');
    //     $sheet->setCellValue('S' . $r(5), 'b. DM');
    //     $sheet->setCellValue('T' . $r(5), 'c. Stroke');
    //     $sheet->setCellValue('U' . $r(5), 'd. Jantung');
    //     $sheet->setCellValue('V' . $r(5), 'f. Kanker');
    //     $sheet->setCellValue('W' . $r(5), 'g. Kolesterol Tinggi');

    //     $sheet->mergeCells('P' . $r(7) . ':Q' . $r(8));
    //     $sheet->setCellValue('P' . $r(7), "Riwayat Diri Sendiri\n(lingkari jika ada)");
    //     $sheet->getStyle('P' . $r(7) . ':Q' . $r(8))->getAlignment()
    //         ->setHorizontal(Alignment::HORIZONTAL_LEFT)
    //         ->setVertical(Alignment::VERTICAL_TOP)
    //         ->setWrapText(true);

    //     $sheet->setCellValue('R' . $r(7), 'a. Hipertensi');
    //     $sheet->setCellValue('S' . $r(7), 'b. DM');
    //     $sheet->setCellValue('T' . $r(7), 'c. Stroke');
    //     $sheet->setCellValue('U' . $r(7), 'd. Jantung');
    //     $sheet->setCellValue('V' . $r(7), 'f. Kanker');
    //     $sheet->setCellValue('W' . $r(7), 'g. Kolesterol Tinggi');

    //     $sheet->mergeCells('P' . $r(9) . ':Q' . $r(12));
    //     $sheet->setCellValue('P' . $r(9), "Perilaku Berisiko Diri Sendiri\n(lingkari jika ada)");
    //     $sheet->getStyle('P' . $r(9) . ':Q' . $r(12))->getAlignment()
    //         ->setHorizontal(Alignment::HORIZONTAL_LEFT)
    //         ->setVertical(Alignment::VERTICAL_TOP)
    //         ->setWrapText(true);

    //     $sheet->setCellValue('R' . $r(9),  'a. Merokok');
    //     $sheet->setCellValue('R' . $r(10), 'b. Konsumsi Tinggi Gula');
    //     $sheet->setCellValue('R' . $r(11), 'c. Konsumsi Tinggi Garam');
    //     $sheet->setCellValue('R' . $r(12), 'd. Konsumsi Tinggi Lemak');

    //     $sheet->setCellValue('X' . $r(9),  ': Ya/Tidak');
    //     $sheet->setCellValue('X' . $r(10), ': Ya/Tidak');
    //     $sheet->setCellValue('X' . $r(11), ': Ya/Tidak');
    //     $sheet->setCellValue('X' . $r(12), ': Ya/Tidak');

    //     // langkah-langkah di AC8–AC12
    //     $steps = [
    //         'AC8'  => ['text' => ': Disi langkah 1', 'color' => 'FFFCE2D2'],
    //         'AC9'  => ['text' => ': Disi langkah 2', 'color' => 'FFFFE79B'],
    //         'AC10' => ['text' => ': Disi langkah 3', 'color' => 'FFFFFFCC'],
    //         'AC11' => ['text' => ': Disi langkah 4', 'color' => 'FFD7E1F3'],
    //         'AC12' => ['text' => ': Disi langkah 5', 'color' => 'FFCCCCFF'],
    //     ];

    //     foreach ($steps as $cell => $v) {
    //         $col = preg_replace('/[0-9]/', '', $cell);
    //         $row = (int) preg_replace('/\D/', '', $cell);
    //         $row = $r($row);
    //         $addr = $col . $row;

    //         $sheet->setCellValue($addr, $v['text']);
    //         $sheet->getStyle($addr)->getFill()
    //             ->setFillType(Fill::FILL_SOLID)
    //             ->getStartColor()->setARGB($v['color']);
    //     }

    //     $sheet->getStyle('P' . $r(5) . ':AA' . $r(12))->getFont()->setSize(11);
    //     $sheet->getStyle('P' . $r(5) . ':AA' . $r(12))->getAlignment()
    //         ->setVertical(Alignment::VERTICAL_TOP)
    //         ->setWrapText(true);

    //     // ==================== HEADER ATAS (TABEL) ====================
    //     $sheet->setCellValue('A' . $r(16), 'Usia Dewasa dan Lansia');
    //     $sheet->mergeCells('A' . $r(16) . ':AC' . $r(16));
    //     $sheet->getStyle('A' . $r(16) . ':AC' . $r(16))->getFont()->setSize(16)->setBold(true);
    //     $sheet->getStyle('A' . $r(16) . ':AC' . $r(16))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    //     $sheet->getStyle('A' . $r(16) . ':AC' . $r(16))->getFill()
    //         ->setFillType(Fill::FILL_SOLID)
    //         ->getStartColor()->setARGB('FFD3D3D3');

    //     // kolom A18
    //     $sheet->mergeCells('A' . $r(18) . ':A' . $r(20));
    //     $sheet->setCellValue('A' . $r(18), "Waktu ke\nPosyandu\n(tanggal/bulan/tahun)");
    //     $sheet->getStyle('A' . $r(18))->getFont()->setBold(true);
    //     $sheet->getStyle('A' . $r(18))->getAlignment()
    //         ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    //         ->setVertical(Alignment::VERTICAL_CENTER)
    //         ->setWrapText(true);

    //     // header besar baris 17
    //     $sheet->mergeCells('A' . $r(17) . ':N' . $r(17));
    //     $sheet->setCellValue('A' . $r(17), "Hasil Penimbangan / Pengukuran / Pemeriksaan\n(Jika hasil pemeriksaan Tekanan Darah/Gula Darah tergolong tinggi maka dirujuk ke Pustu/Puskesmas)");
    //     $sheet->getStyle('A' . $r(17) . ':N' . $r(17))->getFont()->setBold(true)->setSize(11);
    //     $sheet->getStyle('A' . $r(17) . ':N' . $r(17))->getAlignment()
    //         ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    //         ->setVertical(Alignment::VERTICAL_CENTER)
    //         ->setWrapText(true);

    //     $sheet->mergeCells('O' . $r(17) . ':V' . $r(17));
    //     $sheet->setCellValue('O' . $r(17), "Kuesioner PPOK/PUMA (Skoring) ≥ 40 Tahun dan merokok\n(jika sasaran menjawab dengan score >6 , maka sasaran dirujuk ke Pustu/Puskesmas)");
    //     $sheet->getStyle('O' . $r(17) . ':V' . $r(17))->getFont()->setBold(true)->setSize(11);
    //     $sheet->getStyle('O' . $r(17) . ':V' . $r(17))->getAlignment()
    //         ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    //         ->setVertical(Alignment::VERTICAL_CENTER)
    //         ->setWrapText(true);

    //     $sheet->mergeCells('W' . $r(17) . ':Z' . $r(17));
    //     $sheet->setCellValue('W' . $r(17), 'Hasil Wawancara Faktor Risiko PM');
    //     $sheet->getStyle('W' . $r(17) . ':Z' . $r(17))->getFont()->setBold(true)->setSize(12);
    //     $sheet->getStyle('W' . $r(17) . ':Z' . $r(17))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    //     $sheet->mergeCells('AA' . $r(17) . ':AA' . $r(20));
    //     $sheet->setCellValue('AA' . $r(17), "Wawancara Usia Dewasa\nyang menggunakan Alat Kontrasepsi\n(Pil/Kondom/Lainnya)\n(Ya/Tidak)");
    //     $sheet->getStyle('AA' . $r(17))->getAlignment()
    //         ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    //         ->setVertical(Alignment::VERTICAL_CENTER)
    //         ->setWrapText(true);

    //     $sheet->mergeCells('AB' . $r(17) . ':AB' . $r(20));
    //     $sheet->setCellValue('AB' . $r(17), "Edukasi");
    //     $sheet->getStyle('AB' . $r(17))->getAlignment()
    //         ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    //         ->setVertical(Alignment::VERTICAL_CENTER)
    //         ->setWrapText(true);

    //     $sheet->mergeCells('AC' . $r(17) . ':AC' . $r(20));
    //     $sheet->setCellValue('AC' . $r(17), "Rujuk\nPustu/\nPuskesmas");
    //     $sheet->getStyle('AC' . $r(17))->getAlignment()
    //         ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    //         ->setVertical(Alignment::VERTICAL_CENTER)
    //         ->setWrapText(true);

    //     // ==================== KOLOM PENIMBANGAN & PEMERIKSAAN (B–N) ====================
    //     $sheet->mergeCells('B' . $r(18) . ':B' . $r(20)); $sheet->setCellValue('B' . $r(18), "Berat\nBadan\n(Kg)");
    //     $sheet->mergeCells('C' . $r(18) . ':C' . $r(20)); $sheet->setCellValue('C' . $r(18), "Tinggi\nBadan\n(Cm)");
    //     $sheet->mergeCells('D' . $r(18) . ':D' . $r(20)); $sheet->setCellValue('D' . $r(18), "IMT\nSangat Kurus (SK)/\nKurus (K)/\nNormal (N)/\nGemuk (G)/\nObesitas (O)");
    //     $sheet->mergeCells('E' . $r(18) . ':E' . $r(20)); $sheet->setCellValue('E' . $r(18), "Lingkar\nPerut\n(Cm)");
    //     $sheet->mergeCells('F' . $r(18) . ':F' . $r(20)); $sheet->setCellValue('F' . $r(18), "Lingkar\nLengan\nAtas\n(Cm)");
    //     $sheet->mergeCells('G' . $r(18) . ':H' . $r(18)); $sheet->setCellValue('G' . $r(18), 'Tekanan Darah');
    //     $sheet->mergeCells('G' . $r(19) . ':G' . $r(20)); $sheet->setCellValue('G' . $r(19), "Sistole/\nDiastole");
    //     $sheet->mergeCells('H' . $r(19) . ':H' . $r(20)); $sheet->setCellValue('H' . $r(19), "Hasil\n(Rendah/\nNormal/\nTinggi)");
    //     $sheet->mergeCells('I' . $r(18) . ':J' . $r(18)); $sheet->setCellValue('I' . $r(18), 'Gula Darah');
    //     $sheet->mergeCells('I' . $r(19) . ':I' . $r(20)); $sheet->setCellValue('I' . $r(19), "Kadar\nGula Darah\nSewaktu\nmg/dL");
    //     $sheet->mergeCells('J' . $r(19) . ':J' . $r(20)); $sheet->setCellValue('J' . $r(19), "Hasil\n(Rendah/\nNormal/\nTinggi)");
    //     $sheet->mergeCells('K' . $r(18) . ':L' . $r(18)); $sheet->setCellValue('K' . $r(18), 'Tes Hitung Jari Tangan');
    //     $sheet->setCellValue('K' . $r(19), 'Mata Kanan'); $sheet->setCellValue('L' . $r(19), 'Mata Kiri');
    //     $sheet->setCellValue('K' . $r(20), "Normal/\nGangguan"); $sheet->setCellValue('L' . $r(20), "Normal/\nGangguan");
    //     $sheet->mergeCells('M' . $r(18) . ':N' . $r(18)); $sheet->setCellValue('M' . $r(18), 'Tes Berbisik');
    //     $sheet->setCellValue('M' . $r(19), "Telinga\nKanan"); $sheet->setCellValue('N' . $r(19), "Telinga\nKiri");
    //     $sheet->setCellValue('M' . $r(20), "Normal/\nGangguan"); $sheet->setCellValue('N' . $r(20), "Normal/\nGangguan");

    //     // ==================== KUESIONER PUMA (O–V) ====================
    //     $sheet->setCellValue('O' . $r(18), "Jenis\nKelamin");
    //     $sheet->setCellValue('P' . $r(18), "Usia");
    //     $sheet->setCellValue('Q' . $r(18), "Merokok");
    //     $sheet->mergeCells('R' . $r(18) . ':R' . $r(20));
    //     $sheet->setCellValue('R' . $r(18), "Apakah Anda sering merasa\nnapas pendek saat berjalan\ncepat di jalan datar atau\nsedikit menanjak?\n\n(Tidak = 0 | Ya = 5)");
    //     $sheet->mergeCells('S' . $r(18) . ':S' . $r(20));
    //     $sheet->setCellValue('S' . $r(18), "Apakah Anda sering\nmempunyai dahak dari paru\natau sulit mengeluarkan\ndahak saat tidak flu?\n\n(Tidak = 0 | Ya = 4)");
    //     $sheet->mergeCells('T' . $r(18) . ':T' . $r(20));
    //     $sheet->setCellValue('T' . $r(18), "Apakah Anda biasanya\nbatuk saat tidak sedang\nmenderita flu?\n\n(Tidak = 0 | Ya = 4)");
    //     $sheet->mergeCells('U' . $r(18) . ':U' . $r(20));
    //     $sheet->setCellValue('U' . $r(18), "Pernahkah dokter/tenaga\nkesehatan meminta Anda\nmeniup alat spirometri\natau peakflow meter?\n\n(Tidak = 0 | Ya = 5)");
    //     $sheet->mergeCells('V' . $r(18) . ':V' . $r(20));
    //     $sheet->setCellValue('V' . $r(18), "Skor\nPUMA");
    //     $sheet->mergeCells('O' . $r(19) . ':O' . $r(20)); $sheet->setCellValue('O' . $r(19), "Pr = 0\nLk = 1");
    //     $sheet->mergeCells('P' . $r(19) . ':P' . $r(20)); $sheet->setCellValue('P' . $r(19), "40-49 = 0\n50-59 = 1\n≥ 60 = 2");
    //     $sheet->mergeCells('Q' . $r(19) . ':Q' . $r(20)); $sheet->setCellValue('Q' . $r(19), "Tidak = 0\n<20 Bks/Th = 0\n20-39 Bks/Th = 1\n≥40 Bks/Th = 2");
    //     $sheet->setCellValue('R' . $r(20), "Tidak = 0\nYa = 5");
    //     $sheet->setCellValue('S' . $r(20), "Tidak = 0\nYa = 4");
    //     $sheet->setCellValue('T' . $r(20), "Tidak = 0\nYa = 4");
    //     $sheet->setCellValue('U' . $r(20), "Tidak = 0\nYa = 5");
    //     $sheet->mergeCells('V' . $r(19) . ':V' . $r(20)); $sheet->setCellValue('V' . $r(19), "< 6\n≥ 6");

    //     // ==================== SKRINING TBC (W–Z) ====================
    //     $sheet->mergeCells('W' . $r(18) . ':Z' . $r(18));
    //     $sheet->setCellValue('W' . $r(18), 'Skrining Gejala TBC (jika 2 gejala terpenuhi maka dirujuk ke Puskesmas)');
    //     $sheet->getStyle('W' . $r(18) . ':Z' . $r(18))->getFont()->setBold(true);
    //     $sheet->getStyle('W' . $r(18) . ':Z' . $r(18))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    //     $sheet->mergeCells('W' . $r(19) . ':W' . $r(20)); $sheet->setCellValue('W' . $r(19), "Batuk\nterus\nmenerus\n(Ya/Tidak)");
    //     $sheet->mergeCells('X' . $r(19) . ':X' . $r(20)); $sheet->setCellValue('X' . $r(19), "Demam\nlebih dari\n2 minggu\n(Ya/Tidak)");
    //     $sheet->mergeCells('Y' . $r(19) . ':Y' . $r(20)); $sheet->setCellValue('Y' . $r(19), "BB tidak\nnaik atau\nturun dalam\n2 bulan\n(Ya/Tidak)");
    //     $sheet->mergeCells('Z' . $r(19) . ':Z' . $r(20)); $sheet->setCellValue('Z' . $r(19), "Kontak erat\ndengan\nPasien TBC\n(Ya/Tidak)");

    //     // ==================== ISI DATA RIWAYAT (mulai baris 21) ====================
    //     $row = $r(21);

    //     foreach ($periksas as $periksa) {
    //         $sheet->setCellValue("A{$row}", $periksa->tanggal_periksa ?? '-');

    //         $imt = ($periksa->tinggi_badan > 0)
    //             ? round($periksa->berat_badan / (($periksa->tinggi_badan / 100) ** 2), 2)
    //             : 0;

    //         $kategori = $imt < 17   ? 'SK'
    //                   : ($imt < 18.5 ? 'K'
    //                   : ($imt < 25   ? 'N'
    //                   : ($imt < 30   ? 'G' : 'O')));

    //         $sheet->setCellValue("B{$row}", $periksa->berat_badan ?? '');
    //         $sheet->setCellValue("C{$row}", $periksa->tinggi_badan ?? '');
    //         $sheet->setCellValue("D{$row}", $kategori);
    //         $sheet->setCellValue("E{$row}", $periksa->lingkar_perut ?? '');
    //         $sheet->setCellValue("F{$row}", $periksa->lingkar_lengan_atas ?? '');
    //         $sheet->setCellValue("G{$row}", ($periksa->sistole ?? '').'/'.($periksa->diastole ?? ''));
    //         $sheet->setCellValue(
    //             "H{$row}",
    //             ($periksa->sistole >= 140 || $periksa->diastole >= 90) ? 'Tinggi' : 'Normal'
    //         );
    //         $sheet->setCellValue("I{$row}", $periksa->gula_darah ?? '');
    //         $sheet->setCellValue(
    //             "J{$row}",
    //             $periksa->gula_darah > 200 ? 'Tinggi'
    //                 : ($periksa->gula_darah < 70 ? 'Rendah' : 'Normal')
    //         );
    //         $sheet->setCellValue("K{$row}", $periksa->mata_kanan ?? 'Normal');
    //         $sheet->setCellValue("L{$row}", $periksa->mata_kiri ?? 'Normal');
    //         $sheet->setCellValue("M{$row}", $periksa->telinga_kanan ?? 'Normal');
    //         $sheet->setCellValue("N{$row}", $periksa->telinga_kiri ?? 'Normal');

    //         $jkSkor   = ($warga->jenis_kelamin === 'Laki-laki' || $warga->jenis_kelamin === 'L') ? 1 : 0;
    //         $umur     = $warga->tanggal_lahir ? now()->diffInYears($warga->tanggal_lahir) : 0;
    //         $usiaSkor = $umur >= 60 ? 2 : ($umur >= 50 ? 1 : 0);

    //         $merokokSkor = match ($periksa->merokok ?? 0) {
    //             0 => 0,
    //             1 => 0,
    //             2 => 1,
    //             3 => 2,
    //             default => 0,
    //         };

    //         $q1 = ($periksa->puma_napas_pendek ?? 'Tidak') === 'Ya' ? 5 : 0;
    //         $q2 = ($periksa->puma_dahak ?? 'Tidak')        === 'Ya' ? 4 : 0;
    //         $q3 = ($periksa->puma_batuk ?? 'Tidak')        === 'Ya' ? 4 : 0;
    //         $q4 = ($periksa->puma_tes_paru ?? 'Tidak')     === 'Ya' ? 5 : 0;

    //         $totalPuma = $jkSkor + $usiaSkor + $merokokSkor + $q1 + $q2 + $q3 + $q4;

    //         $sheet->setCellValue("O{$row}", $jkSkor);
    //         $sheet->setCellValue("P{$row}", $usiaSkor);
    //         $sheet->setCellValue("Q{$row}", $merokokSkor);
    //         $sheet->setCellValue("R{$row}", $q1 ? 'Ya' : 'Tidak');
    //         $sheet->setCellValue("S{$row}", $q2 ? 'Ya' : 'Tidak');
    //         $sheet->setCellValue("T{$row}", $q3 ? 'Ya' : 'Tidak');
    //         $sheet->setCellValue("U{$row}", $q4 ? 'Ya' : 'Tidak');
    //         $sheet->setCellValue("V{$row}", $totalPuma >= 6 ? '≥ 6' : $totalPuma);

    //         $sheet->setCellValue("W{$row}", $periksa->tbc_batuk       ?? 'Tidak');
    //         $sheet->setCellValue("X{$row}", $periksa->tbc_demam       ?? 'Tidak');
    //         $sheet->setCellValue("Y{$row}", $periksa->tbc_bb_turun    ?? 'Tidak');
    //         $sheet->setCellValue("Z{$row}", $periksa->tbc_kontak_erat ?? 'Tidak');
    //         $sheet->setCellValue("AA{$row}", $periksa->kontrasepsi ?? '-');
    //         $sheet->setCellValue("AB{$row}", $periksa->edukasi     ?? '-');

    //         $rujuk = [];
    //         if (($periksa->sistole >= 140 || $periksa->diastole >= 90) || ($periksa->gula_darah > 200)) {
    //             $rujuk[] = 'TD/Gula Darah Tinggi';
    //         }
    //         if ($totalPuma > 6) {
    //             $rujuk[] = 'Skor PUMA >6';
    //         }

    //         $gejalaTBC = collect([
    //             $periksa->tbc_batuk,
    //             $periksa->tbc_demam,
    //             $periksa->tbc_bb_turun,
    //             $periksa->tbc_kontak_erat,
    //         ])->filter(fn($v) => $v === 'Ya')->count();

    //         if ($gejalaTBC >= 2) {
    //             $rujuk[] = 'Suspek TBC';
    //         }

    //         if (!empty($rujuk)) {
    //             $sheet->setCellValue("AC{$row}", 'YA (' . implode(', ', $rujuk) . ')');
    //             $sheet->getStyle("AC{$row}")
    //                 ->getFont()->setBold(true)->getColor()->setARGB('FFFF0000');
    //         } else {
    //             $sheet->setCellValue(
    //                 "AC{$row}",
    //                 ($periksa->rujuk_puskesmas == 1 ? 'Ya' : 'Tidak')
    //             );
    //         }

    //         $row++;
    //     }

    //     $lastRow = $row - 1;

    //     // ==================== WARNA BACKGROUND & BORDER KARTU INI ====================
    //     $sheet->getStyle('A' . $r(18) . ':A' . $r(20))->getFill()
    //         ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFCE2D2');
    //     $sheet->getStyle('B' . $r(18) . ':B' . $r(20))->getFill()
    //         ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFE79B');
    //     $sheet->getStyle('D' . $r(18) . ':D' . $r(20))->getFill()
    //         ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFFCC');
    //     $sheet->getStyle('E' . $r(18) . ':E' . $r(20))->getFill()
    //         ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFE79B');
    //     $sheet->getStyle('G' . $r(18) . ':G' . $r(20))->getFill()
    //         ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFE79B');
    //     $sheet->getStyle('H' . $r(18) . ':H' . $r(20))->getFill()
    //         ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFE79B');

    //     $sheet->getStyle('I' . $r(18) . ':AA' . $r(20))->getFill()
    //         ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD7E1F3');
    //     $sheet->getStyle('AA' . $r(17) . ':AA' . $r(20))->getFill()
    //         ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD7E1F3');
    //     $sheet->getStyle('AB' . $r(17) . ':AB' . $r(20))->getFill()
    //         ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFCCCCFF');
    //     $sheet->getStyle('AC' . $r(17) . ':AC' . $r(20))->getFill()
    //         ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD7E1F3');
    //     $sheet->getStyle('F' . $r(17) . ':F' . $r(20))->getFill()
    //         ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFE79B');
    //     $sheet->getStyle('C' . $r(17) . ':C' . $r(20))->getFill()
    //         ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFE79B');
    //     $sheet->getStyle('B' . $r(17) . ':H' . $r(20))->getFill()
    //         ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD8D8D8');
    //     $sheet->getStyle('I' . $r(18) . ':N' . $r(20))->getFill()
    //         ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD7E1F3');

    //     $sheet->getStyle('O' . $r(17) . ':V' . $r(20))->getFill()
    //         ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD7E1F3');
    //     $sheet->getStyle('W' . $r(17) . ':Z' . $r(20))->getFill()
    //         ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD7E1F3');

    //     $sheet->getStyle('A' . $r(18) . ':AC' . $r(20))->getFont()->setBold(true);
    //     $sheet->getStyle('A' . $r(16) . ':AC' . $lastRow)->getBorders()->getAllBorders()
    //         ->setBorderStyle(Border::BORDER_THIN);
    //     $sheet->getStyle('A' . $r(18) . ':AC' . $lastRow)->getAlignment()
    //         ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    //         ->setVertical(Alignment::VERTICAL_CENTER)
    //         ->setWrapText(true);

    //     // row height hanya untuk baris header (per kartu)
    //     $sheet->getRowDimension($r(17))->setRowHeight(45);
    //     $sheet->getRowDimension($r(18))->setRowHeight(75);
    //     $sheet->getRowDimension($r(19))->setRowHeight(50);
    //     $sheet->getRowDimension($r(20))->setRowHeight(50);

    //     // autosize kolom (boleh cukup sekali di luar loop, tapi aman juga di sini)
    //     foreach (array_merge(range('A', 'Z'), ['AA', 'AB', 'AC']) as $col) {
    //         $sheet->getColumnDimension($col)->setAutoSize(true);
    //     }

    //     return $lastRow;
    // }

    // /**
    //  * Export: 1 sheet, banyak kartu (ditumpuk ke bawah)
    //  */
    // public function exportKartuExcelSemuaSatuSheet()
    // {
    //     $wargas = Warga::with('pemeriksaanDewasaLansiaAll')
    //         ->whereHas('pemeriksaanDewasaLansiaAll')
    //         ->get();

    //     if ($wargas->isEmpty()) {
    //         abort(404, 'Belum ada data pemeriksaan');
    //     }

    //     $spreadsheet = new Spreadsheet();
    //     $sheet = $spreadsheet->getActiveSheet();
    //     $sheet->setTitle('Kartu Dewasa');

    //     $offset = 0;
    //     $jarakAntarKartu = 5; // baris kosong antar kartu

    //     foreach ($wargas as $warga) {
    //         $lastRow = $this->buildKartuSheetOffset($sheet, $warga, $offset);
    //         $offset = $lastRow + $jarakAntarKartu;
    //     }

    //     $filename = "Kartu_Pemeriksaan_Dewasa_Lansia_MULTI.xlsx";
    //     header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    //     header('Content-Disposition: attachment; filename="' . $filename . '"');
    //     header('Cache-Control: max-age=0');

    //     $writer = new Xlsx($spreadsheet);
    //     $writer->save('php://output');
    //     exit;
    // }
}
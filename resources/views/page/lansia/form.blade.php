{{-- resources/views/pemeriksaan/lansia/_form.blade.php --}}
<form action="@isset($lansia){{ route('lansia.update', $lansia) }}@else{{ route('lansia.store') }}@endisset"
      method="POST" id="ajaxForm" class="space-y-12">

    @csrf
    @if(isset($lansia)) @method('PUT') @endif
    <input type="hidden" name="warga_id" value="{{ $warga->id }}">

    {{-- ================= HEADER ================= --}}
    <div class="bg-gradient-to-r from-purple-100 to-pink-100 p-8 rounded-2xl border-4 border-purple-300">
        <h3 class="text-3xl font-bold text-purple-800">{{ $warga->nama_lengkap }}</h3>
        <p class="text-xl">
            NIK: {{ $warga->nik }}
            |
            Usia:
            {{ $warga->tanggal_lahir ? \Carbon\Carbon::parse($warga->tanggal_lahir)->age : '-' }} tahun
        </p>

        <input
            type="date"
            name="tanggal_periksa"
            required
            class="input input-bordered input-lg mt-6 w-full max-w-xs"
            value="{{ isset($lansia) && $lansia->tanggal_periksa
                ? \Carbon\Carbon::parse($lansia->tanggal_periksa)->format('Y-m-d')
                : now()->format('Y-m-d') }}"
        >
    </div>

    {{-- ================= AKS - CHECKBOX + AUTO HITUNG ================= --}}
    <div class="bg-gradient-to-r from-indigo-50 to-purple-50 p-10 rounded-3xl border-4 border-indigo-300">
        <h2 class="text-4xl font-bold text-center text-indigo-800 mb-6">
            AKS - Aktivitas Kehidupan Sehari-hari (Barthel Index)
        </h2>

        <div class="alert alert-info shadow-lg mb-8 text-center">
            <strong>Pilih SEMUA kondisi yang sesuai dengan lansia</strong>
        </div>

        <div class="grid md:grid-cols-2 gap-8" id="aks-container">
            @php
                $aks = [
                    'BAB' => [
                        'keterangan' => 'Mengendalikan rangsang Buang Air Besar',
                        'options' => [
                            'Tidak terkendali / tak teratur (perlu pencahar)'   => 'bab_s0_tidak_terkendali|0',
                            'Kadang-kadang tak terkendali (≥1x/minggu)'         => 'bab_s1_kadang_tak_terkendali|1',
                            'Terkendali teratur'                                => 'bab_s2_terkendali|2'
                        ],
                    ],
                    'BAK' => [
                        'keterangan' => 'Mengendalikan rangsang Buang Air Kecil',
                        'options' => [
                            'Tidak terkendali / pakai kateter'          => 'bak_s0_tidak_terkendali_kateter|0',
                            'Kadang-kadang tak terkendali (≥1x/24 jam)' => 'bak_s1_kadang_1x24jam|1',
                            'Terkendali & mandiri'                      => 'bak_s2_mandiri|2',
                        ],
                    ],
                    'Membersihkan Diri' => [
                        'keterangan' => 'Mencuci wajah, menyikat rambut, sikat gigi, dll',
                        'options' => [
                            'Butuh bantuan orang lain' => 'diri_s0_butuh_orang_lain|0',
                            'Mandiri'                  => 'diri_s1_mandiri|1',
                        ],
                    ],
                    'Ke WC' => [
                        'keterangan' => 'Pergi ke toilet & membersihkan diri',
                        'options' => [
                            'Tergantung orang lain'                      => 'wc_s0_tergantung_lain|0',
                            'Perlu bantuan tapi bisa sendiri sebagian'   => 'wc_s1_perlu_beberapa_bisa_sendiri|1',
                            'Mandiri'                                    => 'wc_s2_mandiri|2',
                        ],
                    ],
                    'Makan' => [
                        'keterangan' => 'Makan dan minum sendiri',
                        'options' => [
                            'Tidak mampu'                        => 'makan_s0_tidak_mampu|0',
                            'Perlu bantuan (dipotong/disuapi)'   => 'makan_s1_perlu_pemotongan|1',
                            'Mandiri'                            => 'makan_s2_mandiri|2',
                        ],
                    ],
                    'Berpindah' => [
                        'keterangan' => 'Dari tempat tidur ke kursi',
                        'options' => [
                            'Tidak mampu'           => 'bergerak_s0_tidak_mampu|0',
                            'Butuh bantuan 2 orang' => 'bergerak_s1_butuh_2orang|1',
                            'Butuh bantuan 1 orang' => 'bergerak_s2_butuh_1orang|2',
                            'Mandiri'               => 'bergerak_s3_mandiri|3',
                        ],
                    ],
                    'Berjalan' => [
                        'keterangan' => 'Berjalan di lantai datar 50m',
                        'options' => [
                            'Tidak mampu'                             => 'jalan_s0_tidak_mampu|0',
                            'Hanya dengan kursi roda (dorong orang)'  => 'jalan_s1_kursi_roda|1',
                            'Berjalan dengan bantuan 1 orang'         => 'jalan_s2_bantuan_1orang|2',
                            'Mandiri (boleh pakai tongkat)'           => 'jalan_s3_mandiri|3',
                        ],
                    ],
                    'Berpakaian' => [
                        'keterangan' => 'Memakai baju, ikat sepatu, dll',
                        'options' => [
                            'Tergantung orang lain' => 'pakaian_s0_tergantung_lain|0',
                            'Sebagian dibantu'      => 'pakaian_s1_sebagian_dibantu|1',
                            'Mandiri'               => 'pakaian_s2_mandiri|2',
                        ],
                    ],
                    'Naik Tangga' => [
                        'keterangan' => 'Naik turun 1 lantai tangga',
                        'options' => [
                            'Tidak mampu'              => 'tangga_s0_tidak_mampu|0',
                            'Butuh bantuan + pegangan' => 'tangga_s1_butuh_bantuan|1',
                            'Mandiri'                  => 'tangga_s2_mandiri|2',
                        ],
                    ],
                    'Mandi' => [
                        'keterangan' => 'Mandi sendiri (masuk-keluar kamar mandi)',
                        'options' => [
                            'Tergantung orang lain' => 'mandi_s0_tergantung_lain|0',
                            'Mandiri'               => 'mandi_s1_mandiri|1',
                        ],
                    ],
                ];
            @endphp

            @foreach($aks as $kategori => $item)
                <div class="bg-white p-8 rounded-2xl shadow-xl border-2 border-indigo-200">
                    <h4 class="text-2xl font-bold text-indigo-800 mb-4">{{ $kategori }}</h4>
                    <p class="text-gray-700 mb-6 leading-relaxed">{{ $item['keterangan'] }}</p>
                    <div class="space-y-5">
                        @foreach($item['options'] as $label => $data)
                            @php
                                [$field, $skor] = explode('|', $data);
                                $fieldName = 'aks_'.$field;
                            @endphp

                            <label class="flex items-center gap-4 cursor-pointer hover:bg-indigo-50 p-4 rounded-xl transition">
                                <input
                                    type="checkbox"
                                    name="{{ $fieldName }}"
                                    value="1"
                                    data-skor="{{ $skor }}"
                                    class="checkbox checkbox-lg checkbox-primary aks-checkbox"
                                    {{ isset($lansia) && $lansia->{$fieldName} ? 'checked' : '' }}
                                >
                                <div>
                                    <span class="text-lg font-medium">{{ $label }}</span>
                                    <span class="badge badge-primary ml-3">Skor {{ $skor }}</span>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        {{-- HASIL AKS --}}
        <div class="mt-12 p-8 bg-gradient-to-r from-indigo-900 to-purple-900 rounded-3xl text-white text-center" id="aks-result-box">
            <h3 class="text-4xl font-bold mb-6">HASIL AKS (Barthel Index)</h3>
            <div class="text-6xl font-black" id="total-skor">0</div>
            <div class="text-3xl mt-4 font-bold" id="kategori-aks">Total Ketergantungan (T)</div>

            <div class="mt-6 grid grid-cols-5 gap-4 text-sm">
                <div class="bg-green-600 p-4 rounded-xl">M = 20<br><small>Mandiri</small></div>
                <div class="bg-lime-600 p-4 rounded-xl">R = 12–19<br><small>Risiko Ringan</small></div>
                <div class="bg-yellow-600 p-4 rounded-xl">S = 9–11<br><small>Sedang</small></div>
                <div class="bg-orange-600 p-4 rounded-xl">B = 5–8<br><small>Berat</small></div>
                <div class="bg-red-700 p-4 rounded-xl">T = 0–4<br><small>Total</small></div>
            </div>

            <div id="rujuk-warning" class="mt-6 text-2xl font-bold hidden">
                <span class="text-red-300">WAJIB RUJUK KE PUSKESMAS / RS!</span>
            </div>

            {{-- ================= AKS - EDUKASI & CATATAN ================= --}}
            <div class="mt-8 p-6 bg-gradient-to-r from-indigo-100 to-purple-100 rounded-2xl border-2 border-indigo-300">
                <h3 class="text-2xl font-bold text-indigo-900 mb-4">Edukasi & Catatan AKS</h3>
                <div class="grid md:grid-cols-2 gap-6">
                    <div>
                        <label class="label">
                            <span class="label-text font-semibold">Edukasi yang diberikan (AKS)</span>
                        </label>
                        <textarea name="aks_edukasi" class="textarea textarea-bordered w-full h-32" placeholder="Contoh: Latihan keseimbangan, hindari jatuh, dll">{{ old('aks_edukasi', $lansia->aks_edukasi ?? '') }}</textarea>
                    </div>
                    <div>
                        <label class="label">
                            <span class="label-text font-semibold">Catatan tambahan AKS</span>
                        </label>
                        <textarea name="aks_catatan" class="textarea textarea-bordered w-full h-32" placeholder="Catatan lain...">{{ old('aks_catatan', $lansia->aks_catatan ?? '') }}</textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ================= SKILAS ================= --}}
    <div class="bg-gradient-to-r from-red-50 to-pink-50 p-10 rounded-3xl border-4 border-red-300 mt-12">
        <h2 class="text-4xl font-bold text-center text-red-800 mb-6">
            SKILAS - Skrining Risiko Lansia
        </h2>
        <div class="alert alert-error shadow-lg mb-8 text-center text-white bg-red-600 font-bold py-4">
            Jika ADA 1 atau lebih jawaban "Ya" → WAJIB RUJUK!
        </div>

        @php
            $skilasGroups = [
                ['title' => 'Penurunan Kognitif', 'items' => [
                    ['field' => 'orientasi_waktu_tempat', 'label' => 'Tidak tahu hari/tanggal/tahun atau tempat sekarang'],
                    ['field' => 'mengulang_ketiga_kata', 'label' => 'Tidak bisa mengulang 3 kata yang baru diajarkan'],
                ]],
                ['title' => 'Keterbatasan Mobilisasi', 'items' => [
                    ['field' => 'tes_berdiri_dari_kursi', 'label' => 'Tidak bisa berdiri dari kursi 5x dalam 10 detik'],
                ]],
                ['title' => 'Malnutrisi', 'items' => [
                    ['field' => 'bb_berkurang_3kg_dalam_3bulan', 'label' => 'Berat badan turun ≥3 kg dalam 3 bulan terakhir / pakaian jadi lebih longgar'],
                    ['field' => 'hilang_nafsu_makan', 'label' => 'Nafsu makan sangat menurun / kesulitan makan'],
                    ['field' => 'lla_kurang_21cm', 'label' => 'Lingkar lengan atas (LiLA) < 21 cm'],
                ]],
                ['title' => 'Gangguan Penglihatan', 'items' => [
                    ['field' => 'masalah_pada_mata', 'label' => 'Ada keluhan penglihatan (sulit melihat jauh, membaca, penyakit mata, dsb)'],
                    ['field' => 'tes_melihat', 'label' => 'Tidak bisa baca huruf kecil dengan kacamata'],
                ]],
                ['title' => 'Gangguan Pendengaran', 'items' => [
                    ['field' => 'tes_bisik', 'label' => 'Tidak bisa dengar bisikan dari 1 meter'],
                ]],
                ['title' => 'Gejala Depresi dalam 2 minggu terakhir', 'items' => [
                    ['field' => 'perasaan_sedih_tertekan', 'label' => 'Sering merasa sedih, tertekan, atau putus asa'],
                    ['field' => 'sedikit_minat_atau_kenikmatan', 'label' => 'Kehilangan minat atau kesenangan dalam hampir semua aktivitas'],
                ]],
                ['title' => 'Status Imunisasi', 'items' => [
                    ['field' => 'imunisasi_covid', 'label' => 'Belum pernah vaksin COVID-19'],
                ]],
            ];
        @endphp

        <div class="grid md:grid-cols-2 gap-8">
            @foreach($skilasGroups as $group)
                <div class="bg-white p-7 rounded-2xl shadow-2xl border-2 border-gray-300">
                    <h3 class="text-2xl font-bold text-red-800 mb-6">{{ $group['title'] }}</h3>
                    <div class="space-y-8">
                        @foreach($group['items'] as $item)
                            @php
                                $field      = $item['field'];
                                $isTesBisik = $field === 'tes_bisik';
                                $isEdit     = !is_null($lansia->id ?? null);

                                if ($isTesBisik) {
                                    $valYa     = old('skil_tes_bisik', $lansia['skil_tes_bisik'] ?? 0) == 1;
                                    $valTidak  = old('skil_tes_bisik_tidak', $lansia['skil_tes_bisik_tidak'] ?? 0) == 1;
                                    $valTdkDpt = old('skil_tidak_dapat_dilakukan', $lansia['skil_tidak_dapat_dilakukan'] ?? 0) == 1;
                                } else {
                                    if (old("skil_{$field}") !== null || old("skil_{$field}_tidak") !== null) {
                                        $valYa    = old("skil_{$field}") == 1;
                                        $valTidak = old("skil_{$field}_tidak") == 1;
                                    } elseif ($isEdit) {
                                        $dbYa     = ($lansia["skil_{$field}"] ?? 0) == 1;
                                        $valYa    = $dbYa;
                                        $valTidak = !$dbYa;
                                    } else {
                                        $valYa    = false;
                                        $valTidak = false;
                                    }
                                }
                            @endphp

                            <div class="pb-6 border-b border-gray-200 last:border-0">
                                <p class="font-medium text-gray-700 mb-4">{{ $item['label'] }}</p>
                                <div class="flex flex-wrap gap-6">
                                    @if($isTesBisik)
                                        <!-- Tes Bisik -->
                                        <label class="flex items-center space-x-3 cursor-pointer">
                                            <input type="checkbox" name="skil_tes_bisik" value="1" class="checkbox checkbox-error checkbox-lg"
                                                   {{ $valYa ? 'checked' : '' }} onclick="uncheckGroup(this, ['skil_tes_bisik_tidak','skil_tidak_dapat_dilakukan'])">
                                            <span class="text-lg font-bold text-red-600">Ya</span>
                                        </label>
                                        <label class="flex items-center space-x-3 cursor-pointer">
                                            <input type="checkbox" name="skil_tes_bisik_tidak" value="1" class="checkbox checkbox-success checkbox-lg"
                                                   {{ $valTidak ? 'checked' : '' }} onclick="uncheckGroup(this, ['skil_tes_bisik','skil_tidak_dapat_dilakukan'])">
                                            <span class="text-lg font-bold text-green-600">Tidak</span>
                                        </label>
                                        <label class="flex items-center space-x-3 cursor-pointer">
                                            <input type="checkbox" name="skil_tidak_dapat_dilakukan" value="1" class="checkbox checkbox-warning checkbox-lg"
                                                   {{ $valTdkDpt ? 'checked' : '' }} onclick="uncheckGroup(this, ['skil_tes_bisik','skil_tes_bisik_tidak'])">
                                            <span class="text-lg font-bold text-yellow-600">Tidak dapat dilakukan</span>
                                        </label>
                                    @else
                                        <label class="flex items-center space-x-3 cursor-pointer">
                                            <input type="checkbox" name="skil_{{ $field }}" value="1" class="checkbox checkbox-error checkbox-lg"
                                                   {{ $valYa ? 'checked' : '' }} onclick="uncheckSibling(this, 'skil_{{ $field }}_tidak')">
                                            <span class="text-lg font-bold text-red-600">Ya</span>
                                        </label>
                                        <label class="flex items-center space-x-3 cursor-pointer">
                                            <input type="checkbox" name="skil_{{ $field }}_tidak" value="1" class="checkbox checkbox-success checkbox-lg"
                                                   {{ $valTidak ? 'checked' : '' }} onclick="uncheckSibling(this, 'skil_{{ $field }}')">
                                            <span class="text-lg font-bold text-green-600">Tidak</span>
                                        </label>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
        
    </div>

    {{-- ================= SKILAS - EDUKASI & CATATAN ================= --}}
    <div class="mt-12 p-6 bg-gradient-to-r from-red-100 to-pink-100 rounded-2xl border-2 border-red-300">
        <h3 class="text-2xl font-bold text-red-900 mb-4">Edukasi & Catatan SKILAS</h3>
        <div class="grid md:grid-cols-2 gap-6">
            <div>
                <label class="label">
                    <span class="label-text font-semibold">Edukasi yang diberikan (SKILAS)</span>
                </label>
                <textarea name="skil_edukasi" class="textarea textarea-bordered w-full h-32" placeholder="Contoh: Imunisasi, gizi, dll">{{ old('skil_edukasi', $lansia->skil_edukasi ?? '') }}</textarea>
            </div>
            <div>
                <label class="label">
                    <span class="label-text font-semibold">Catatan tambahan SKILAS</span>
                </label>
                <textarea name="skil_catatan" class="textarea textarea-bordered w-full h-32" placeholder="Catatan lain...">{{ old('skil_catatan', $lansia->skil_catatan ?? '') }}</textarea>
            </div>
        </div>
    </div>
</div>


    {{-- ================= BUTTON ================= --}}
    <div class="flex justify-center gap-8 mt-12">
        <button type="button" onclick="tutupModal()" class="btn btn-lg btn-ghost">
            Batal
        </button>
        <button type="submit" class="btn btn-lg btn-primary text-xl px-16">
            SIMPAN PEMERIKSAAN
        </button>
    </div>
</form>
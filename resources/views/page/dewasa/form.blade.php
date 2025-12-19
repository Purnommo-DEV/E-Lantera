<form id="ajaxForm"
      action="{{ isset($periksa) ? route('dewasa.update.ajax', [$warga, $periksa]) : route('dewasa.store.ajax', $warga) }}"
      method="POST"
      class="space-y-8 max-w-full">
    @csrf
    @if(isset($periksa)) @method('PUT') @endif

    <!-- HEADER + USIA & JK OTOMATIS -->
    <div class="bg-gradient-to-r from-teal-700 to-teal-900 text-white p-6 md:p-8 rounded-2xl md:-mx-4 md:-mt-4 mb-8 shadow-2xl">
        <h2 class="text-2xl md:text-4xl font-bold break-words">{{ $warga->nama }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mt-4 md:mt-6 text-sm md:text-lg">
            <div class="truncate">
                NIK: <span class="font-bold break-all">{{ $warga->nik }}</span>
            </div>
            <div class="truncate">
                JK:
                <span class="font-bold">
                    {{ $warga->jenis_kelamin }}
                    (<span id="jk_skor">{{ $warga->jenis_kelamin == 'Laki-laki' ? '1' : '0' }}</span>)
                </span>
            </div>
            <div>
                Usia:
                <span class="font-bold text-yellow-300 text-2xl md:text-3xl" id="usia_display">-</span>
            </div>
            <div>
                Skor Usia PUMA:
                <span class="font-bold text-orange-300 text-xl md:text-2xl" id="skor_usia_display">-</span>
            </div>
        </div>
        <input type="hidden" name="jk_puma" id="jk_puma">
        <input type="hidden" name="usia_puma" id="usia_puma">
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div>
            <label class="label text-red-600 font-bold text-sm md:text-lg">* Tanggal Periksa</label>
            <input type="date" name="tanggal_periksa" id="tanggal_periksa"
                   value="{{ isset($periksa) ? $periksa->tanggal_periksa->format('Y-m-d') : now()->format('Y-m-d') }}"
                   class="input input-bordered text-base md:text-lg w-full"
                   required>
        </div>
    </div>

    <!-- ANTROPOMETRI & IMT -->
    <div class="bg-blue-50 border-4 border-blue-400 p-6 md:p-8 rounded-2xl mb-8">
        <h3 class="text-xl md:text-2xl font-bold text-blue-900 mb-4 md:mb-6">ANTROPOMETRI & TEKANAN DARAH</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
            <div>
                <label class="label font-semibold text-sm md:text-base">Berat Badan (Kg)</label>
                <input type="number" step="0.1" name="berat_badan" id="berat_badan"
                       value="{{ $periksa->berat_badan ?? '' }}"
                       class="input input-bordered text-base md:text-lg w-full"
                       required>
            </div>
            <div>
                <label class="label font-semibold text-sm md:text-base">Tinggi Badan (Cm)</label>
                <input type="number" step="0.1" name="tinggi_badan" id="tinggi_badan"
                       value="{{ $periksa->tinggi_badan ?? '' }}"
                       class="input input-bordered text-base md:text-lg w-full"
                       required>
            </div>
            <div>
                <label class="label font-semibold text-sm md:text-base">IMT (Otomatis)</label>
                <input type="text" id="imt_display"
                       class="input input-bordered bg-gray-200 font-bold text-xl md:text-2xl text-center w-full"
                       readonly value="-">
                <input type="hidden" name="imt" id="imt_value">
            </div>
            <div>
                <label class="label font-semibold text-sm md:text-base">Kategori IMT</label>
                <input type="text" id="kategori_imt_display"
                       class="input input-bordered bg-yellow-200 font-bold text-lg md:text-xl text-center w-full"
                       readonly value="Isi BB & TB">
                <input type="hidden" name="kategori_imt" id="kategori_imt">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mt-6 md:mt-8">
            <div>
                <input type="number" name="lingkar_perut"
                       value="{{ $periksa->lingkar_perut ?? '' }}"
                       placeholder="Lingkar Perut (Cm)"
                       class="input input-bordered w-full"
                       required>
            </div>
            <div>
                <input type="number" name="lingkar_lengan_atas"
                       value="{{ $periksa->lingkar_lengan_atas ?? '' }}"
                       placeholder="LLA (Cm)"
                       class="input input-bordered w-full"
                       required>
            </div>
            <div>
                <label class="label font-semibold text-sm md:text-base">Tekanan Darah</label>
                <div class="flex flex-wrap md:flex-nowrap items-center gap-2 md:gap-3">
                    <input type="number" name="sistole" id="sistole"
                           value="{{ $periksa->sistole ?? '' }}"
                           class="input input-bordered w-full md:w-28 lg:w-32 text-base md:text-lg"
                           required>
                    <span class="text-xl md:text-2xl">/</span>
                    <input type="number" name="diastole" id="diastole"
                           value="{{ $periksa->diastole ?? '' }}"
                           class="input input-bordered w-full md:w-28 lg:w-32 text-base md:text-lg"
                           required>
                </div>
            </div>
            <div>
                <label class="label font-semibold text-sm md:text-base">Hasil TD</label>
                <input type="text" id="td_hasil"
                       class="input input-bordered bg-red-100 font-bold text-lg md:text-xl text-center w-full"
                       readonly value="Normal (N)">
                <input type="hidden" name="td_kategori" id="td_kategori">
            </div>
        </div>
    </div>

    <!-- GULA DARAH -->
    <div class="bg-purple-50 border-4 border-purple-400 p-6 md:p-8 rounded-2xl mb-8">
        <h3 class="text-xl md:text-2xl font-bold text-purple-900 mb-4 md:mb-6">GULA DARAH SEWAKTU</h3>
        <div class="flex flex-col md:flex-row gap-4 md:gap-10 items-stretch md:items-end">
            <div class="flex-1">
                <label class="label font-semibold text-sm md:text-base">Kadar Gula Darah (mg/dL)</label>
                <input type="number" name="gula_darah" id="gula_darah"
                       value="{{ $periksa->gula_darah ?? '' }}"
                       class="input input-bordered text-base md:text-lg w-full"
                       required>
            </div>
            <div class="w-full md:w-60">
                <label class="label font-semibold text-sm md:text-base">Hasil</label>
                <input type="text" id="gula_hasil"
                       class="input input-bordered w-full bg-pink-100 font-bold text-lg md:text-xl text-center"
                       readonly value="Normal (N)">
                <input type="hidden" name="gd_kategori" id="gula_kategori">
            </div>
        </div>
    </div>

    <!-- TES MATA & TELINGA -->
    <div class="bg-emerald-50 border-4 border-emerald-400 p-6 md:p-8 rounded-2xl mb-8">
        <h3 class="text-xl md:text-2xl font-bold text-emerald-900 mb-4 md:mb-6">TES HITUNG JARI TANGAN</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
            @foreach(['mata_kanan'=>'Mata Kanan', 'mata_kiri'=>'Mata Kiri', 'telinga_kanan'=>'Telinga Kanan', 'telinga_kiri'=>'Telinga Kiri'] as $f=>$l)
            <div>
                <label class="label font-semibold text-sm md:text-base">{{ $l }}</label>
                <select name="{{ $f }}" class="select select-bordered w-full text-sm md:text-base">
                    <option value="Normal"   {{ ($periksa->{$f} ?? '') == 'N'   ? 'selected' : '' }}>Normal (N)</option>
                    <option value="Gangguan" {{ ($periksa->{$f} ?? '') == 'G' ? 'selected' : '' }}>Gangguan (G)</option>
                </select>
            </div>
            @endforeach
        </div>
    </div>

    <!-- SKRINING PUMA -->
    <div class="bg-orange-50 border-4 border-orange-500 p-6 md:p-8 rounded-2xl mb-8">
        <h3 class="text-2xl md:text-3xl font-bold text-orange-900 mb-6 md:mb-8 text-center">
            SKRINING RISIKO PPOK — PUMA
        </h3>

        <div class="bg-white p-4 md:p-6 rounded-xl border-2 border-orange-400 mb-6 md:mb-8">
            <label class="label font-bold text-sm md:text-lg">Merokok</label>
            <select name="merokok" id="merokok" class="select select-bordered w-full text-sm md:text-lg">
                <option value="0" {{ ($periksa->merokok ?? '0') == '0' ? 'selected' : '' }}>
                    Tidak merokok atau &lt;20 batang/hari (=0)
                </option>
                <option value="1" {{ ($periksa->merokok ?? '') == '1' ? 'selected' : '' }}>
                    20–39 batang/hari (=1)
                </option>
                <option value="2" {{ ($periksa->merokok ?? '') == '2' ? 'selected' : '' }}>
                    ≥40 batang/hari (=2)
                </option>
            </select>
        </div>

        <div class="space-y-4 md:space-y-6 text-sm md:text-lg font-medium">
            @php
                $puma = [
                    'napas_pendek' => 'Apakah Anda Pernah merasa napas pendek ketika berjalan lebih cepat pada jalan yang datar atau pada jalan yang sedikit menanjak?',
                    'dahak'        => 'Apakah anda mempunyai dahak yang berasal dari paru atau kesulitan mengeluarkan dahak saat Anda sedang tidak menderita flu?',
                    'batuk'        => 'Apakah anda Biasanya batuk saat Anda sedang tidak menderita flu?',
                    'spirometri'   => 'Apakah Dokter atau tenaga kesehatan lainnya pernah meminta Anda untuk melakukan pemeriksaan spirometri atau peakflow meter (meniup ke dalam suatu alat)?'
                ];
            @endphp
            @foreach($puma as $key => $teks)
            <label class="flex flex-col md:flex-row items-start gap-3 md:gap-6 cursor-pointer bg-white p-4 md:p-5 rounded-xl">
                <input type="checkbox" name="puma_{{ $key }}"
                       class="checkbox checkbox-warning checkbox-md md:checkbox-lg mt-1"
                       {{ isset($periksa) && $periksa->{'puma_'.$key} == '1' ? 'checked' : '' }}>
                <span class="leading-snug">
                    {{ $loop->iteration }}. {{ $teks }}<br>
                    <span class="text-xs md:text-sm text-gray-600">Tidak = 0  Ya = 1</span>
                </span>
            </label>
            @endforeach
        </div>

        <div class="mt-6 md:mt-10 p-6 md:p-8 bg-orange-900 text-white rounded-2xl text-center font-bold text-2xl md:text-3xl">
            <div class="mb-2 md:mb-3">
                TOTAL SKOR PUMA:
                <span id="total_puma" class="text-3xl md:text-5xl align-middle">0</span>
            </div>
            <span id="hasil_puma" class="text-xl md:text-4xl mt-2 md:mt-4 block">
                Risiko Rendah
            </span>
            <input type="hidden" name="skor_puma" id="skor_puma_value">
        </div>
    </div>

    <!-- SKRINING TBC -->
    <div class="bg-red-50 border-4 border-red-500 p-6 md:p-8 rounded-2xl mb-8">
        <h3 class="text-2xl md:text-3xl font-bold text-red-900 mb-6 md:mb-8 text-center">
            SKRINING GEJALA TBC
        </h3>
        <div class="space-y-4 md:space-y-6">
            @foreach([
                'batuk'    => 'Batuk terus menerus ≥ 2 minggu',
                'demam'    => 'Demam ≥ 2 minggu',
                'bb_turun' => 'Berat badan turun tanpa sebab dalam 2 bulan terakhir',
                'kontak'   => 'Kontak erat dengan penderita TBC'
            ] as $k => $t)
            <div class="flex flex-col md:flex-row md:justify-between md:items-center bg-white p-4 md:p-6 rounded-xl gap-3 md:gap-6">
                <span class="font-semibold text-sm md:text-lg leading-snug">
                    {{ $loop->iteration }}. {{ $t }}
                </span>
                <div class="flex gap-6 md:gap-10">
                    <label class="flex items-center gap-2 text-sm md:text-base">
                        <input type="radio" name="tbc_{{ $k }}" value="Ya"
                               {{ isset($periksa) && $periksa->{'tbc_'.$k} == 'Ya' ? 'checked' : '' }}
                               class="radio radio-error radio-sm md:radio-md">
                        <span>Ya</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm md:text-base">
                        <input type="radio" name="tbc_{{ $k }}" value="Tidak"
                               {{ !isset($periksa) || $periksa->{'tbc_'.$k} != 'Ya' ? 'checked' : '' }}
                               class="radio radio-success radio-sm md:radio-md">
                        <span>Tidak</span>
                    </label>
                </div>
            </div>
            @endforeach
        </div>
        <div class="mt-6 md:mt-10 p-6 md:p-8 bg-red-900 text-white rounded-2xl text-center font-bold text-2xl md:text-3xl">
            HASIL:
            <span id="hasil_tbc" class="block mt-2 md:mt-3 text-2xl md:text-4xl">
                Tidak ada gejala
            </span>
        </div>
    </div>

    <!-- KONTRASEPSI, EDUKASI, CATATAN, RUJUK -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-8 mt-6 md:mt-10">
        <div>
            <label class="label font-bold text-sm md:text-lg">Wawancara Kontrasepsi</label>
            <select name="wawancara_kontrasepsi" class="select select-bordered text-sm md:text-lg w-full">
                <option value="Ya" {{ isset($periksa) && $periksa->wawancara_kontrasepsi == 'Ya' ? 'selected' : '' }}>Ya</option>
                <option value="Tidak" {{ !isset($periksa) || $periksa->wawancara_kontrasepsi != 'Ya' ? 'selected' : '' }}>Tidak</option>
            </select>
            <input type="text" name="jenis_kontrasepsi"
                   value="{{ $periksa->jenis_kontrasepsi ?? '' }}"
                   placeholder="Jenis kontrasepsi"
                   class="input input-bordered mt-3 md:mt-4 w-full">
        </div>
        <div>
            <label class="label font-bold text-sm md:text-lg">Edukasi yang Diberikan</label>
            <textarea name="edukasi"
                      class="textarea textarea-bordered h-28 md:h-32 w-full text-sm md:text-base">{{ $periksa->edukasi ?? '' }}</textarea>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-8 mt-4 md:mt-6">
        <div>
            <label class="label font-bold text-sm md:text-lg">Catatan Tambahan</label>
            <textarea name="catatan"
                      class="textarea textarea-bordered h-28 md:h-32 w-full text-sm md:text-base">{{ $periksa->catatan ?? '' }}</textarea>
        </div>
        <div class="flex justify-end items-end">
            <label class="cursor-pointer label gap-4 md:gap-6 text-lg md:text-2xl">
                <span class="font-bold text-red-600 break-words max-w-[14rem] md:max-w-none">
                    RUJUK MANUAL KE PUSKESMAS
                </span>
                <input type="checkbox" name="rujuk_puskesmas"
                       {{ isset($periksa) && $periksa->rujuk_puskesmas ? 'checked' : '' }}
                       class="checkbox checkbox-error checkbox-md md:checkbox-lg">
            </label>
        </div>
    </div>

    <div class="modal-action justify-end gap-4 md:gap-8 mt-8 md:mt-12">
        <button type="button"
                class="btn btn-ghost btn-md md:btn-lg px-8 md:px-12"
                onclick="tutupModal()">
            Batal
        </button>
        <button type="submit"
                id="btnSubmit"
                class="btn btn-primary btn-md md:btn-lg px-12 md:px-20 text-xl md:text-2xl font-bold flex items-center gap-3">
            <span id="btnText">
                {{ isset($periksa) ? 'UPDATE' : 'SIMPAN' }} HASIL
            </span>

            <!-- Spinner (hidden default) -->
            <span id="btnSpinner" class="loading loading-spinner loading-md hidden"></span>
        </button>
    </div>
</form>

<!-- SCRIPT FINAL — PASTI JALAN SETELAH DI-LOAD VIA AJAX -->
<script>
(function () {
    console.clear();
    console.log("FORM DEWASA BERHASIL DIAKTIFKAN VIA AJAX — SEMUA OTOMATIS!");

    const tanggalLahirStr = "{{ $warga->tanggal_lahir ? $warga->tanggal_lahir->format('Y-m-d') : '2000-01-01' }}";
    const lahir = new Date(tanggalLahirStr);

    document.getElementById('ajaxForm').addEventListener('submit', function () {
        const btn = document.getElementById('btnSubmit');
        const text = document.getElementById('btnText');
        const spinner = document.getElementById('btnSpinner');

        // Disable tombol
        btn.disabled = true;

        // Ganti teks + tampilkan spinner
        text.textContent = 'Menyimpan...';
        spinner.classList.remove('hidden');
    });

    window.hitung = function () {
        try {
            // === USIA ===
            const tglVal = document.getElementById('tanggal_periksa')?.value;
            const tgl = tglVal ? new Date(tglVal) : new Date();
            let usia = tgl.getFullYear() - lahir.getFullYear();
            const m = tgl.getMonth() - lahir.getMonth();
            if (m < 0 || (m === 0 && tgl.getDate() < lahir.getDate())) usia--;
            document.getElementById('usia_display').textContent = usia + ' tahun';

            // === SKOR USIA PUMA SESUAI RANGE RESMI ===
            let skorUsia = 0;
            if (usia >= 60) {
                skorUsia = 2;
            } else if (usia >= 50) {
                skorUsia = 1;
            } else {
                skorUsia = 0; // <50 tahun → 0 poin (termasuk 40-49)
            }

            document.getElementById('skor_usia_display').textContent = skorUsia;
            document.getElementById('usia_puma').value = skorUsia;
            document.getElementById('jk_puma').value = "{{ $warga->jenis_kelamin }}" === "Laki-laki" ? 1 : 0;

            // === IMT ===
            const bb = parseFloat(document.getElementById('berat_badan')?.value) || 0;
            const tb = parseFloat(document.getElementById('tinggi_badan')?.value) || 0;
            if (bb > 0 && tb > 0 && tb < 300) {
                const imt = bb / Math.pow(tb / 100, 2);
                const fix = imt.toFixed(2);
                document.getElementById('imt_value').value = fix;
                document.getElementById('imt_display').value = fix;
                const kat = imt < 17 ? 'Sangat Kurus (SK)' :
                           imt < 18.5 ? 'Kurus (K)' :
                           imt < 25 ? 'Normal (N)' :
                           imt < 30 ? 'Gemuk (G)' : 'Obesitas (O)';
                document.getElementById('kategori_imt').value = kat;
                document.getElementById('kategori_imt_display').value = kat;
            } else {
                document.getElementById('imt_display').value = '-';
                document.getElementById('kategori_imt_display').value = 'Isi BB & TB';
            }

            // === TD ===
            const sist = parseInt(document.getElementById('sistole')?.value) || 0;
            const dias = parseInt(document.getElementById('diastole')?.value) || 0;
            const td = sist >= 140 || dias >= 90 ? 'Tinggi (T)' : (sist < 90 || dias < 60 ? 'Rendah (R)' : 'Normal (N)');
            document.getElementById('td_hasil').value = td;
            document.getElementById('td_kategori').value = td;

            // === GULA ===
            const gula = parseInt(document.getElementById('gula_darah')?.value) || 0;
            const gKat = gula > 200 ? 'Tinggi (T)' : (gula < 70 ? 'Rendah (R)' : 'Normal (N)');
            document.getElementById('gula_hasil').value = gKat;
            document.getElementById('gula_kategori').value = gKat;

            // === PUMA ===
            const skorMerokok = parseInt(document.getElementById('merokok')?.value || 0);
            const skorCheck = document.querySelectorAll('input[name^="puma_"]:checked').length;
            const totalPuma = skorMerokok + skorCheck + skorUsia + (document.getElementById('jk_puma').value == 1 ? 1 : 0);
            document.getElementById('total_puma').textContent = totalPuma;
            document.getElementById('hasil_puma').textContent = totalPuma >= 6 ? 'RISIKO TINGGI – RUJUK!' : 'Risiko Rendah';
            document.getElementById('skor_puma_value').value = totalPuma;

            // === TBC ===
            const yaTBC = document.querySelectorAll('input[value="Ya"]:checked').length;
            document.getElementById('hasil_tbc').textContent = yaTBC >= 2 ? 'WAJIB DIRUJUK!' : 'Tidak ada gejala';

        } catch (e) {
            console.error("Error di hitung():", e);
        }
    };

    // Jalankan pertama kali
    window.hitung();

    // Event listener semua input & select
    document.querySelectorAll('input, select').forEach(el => {
        el.addEventListener('input', window.hitung);
        el.addEventListener('change', window.hitung);
    });

    console.log("FORM DEWASA 100% AKTIF — IMT, USIA, PUMA, TBC SUDAH OTOMATIS!");
})();
</script>

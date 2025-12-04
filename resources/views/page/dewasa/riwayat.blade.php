{{-- resources/views/page/dewasa/riwayat.blade.php --}}
<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h3 class="text-2xl font-bold text-indigo-700">{{ $warga->nama }}</h3>
            <p class="text-gray-600">NIK: {{ $warga->nik }} • Usia: {{ $warga->usia ?? (optional($warga->tanggal_lahir)?->age ?? '-') }} tahun</p>
        </div>

        <div class="flex items-center gap-3">
            <button onclick="bukaFormTambah({{ $warga->id }})"
                    class="btn btn-success btn-md">
                + Periksa Baru
            </button>
        </div>
    </div>

    @if($riwayat->count() == 0)
        <div class="text-center py-12 text-gray-500 text-xl">
            Belum ada riwayat pemeriksaan
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="table table-zebra w-full">
                <thead class="bg-indigo-100 text-indigo-900">
                    <tr>
                        <th>Tanggal</th>
                        <th>IMT</th>
                        <th>TD</th>
                        <th>Gula</th>
                        <th>Skor PUMA</th>
                        <th>TBC</th>
                        <th>Rujuk</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($riwayat as $p)
                    <tr>
                        <td>{{ data_get($p, 'tanggal_periksa') ? \Carbon\Carbon::parse($p['tanggal_periksa'])->format('d/m/Y') : '-' }}</td>
                        <td class="text-center">
                            {!! data_get($p, 'imt_badge_html') ?? '<span class="text-gray-400">-</span>' !!}
                        </td>
                        <td class="text-center">
                            {!! data_get($p, 'td_badge_html') ?? '<span class="text-gray-400">-</span>' !!}
                        </td>
                        <td class="text-center">
                            {!! (data_get($p, 'gula_kategori') ?? '') === 'T' ? '<span class="text-red-600 font-bold">Tinggi</span>' : 'Normal' !!}
                        </td>
                        <td class="text-center font-bold {{ (data_get($p,'skor_puma',0) >= 6) ? 'text-red-600' : '' }}">{{ data_get($p,'skor_puma',0) }}</td>
                        <td class="text-center">{!! data_get($p,'tbc_rujuk') ? '<span class="text-red-600 font-bold">YA</span>' : '-' !!}</td>
                        <td class="text-center font-bold text-lg">
                            {!! (data_get($p,'rujuk_puskesmas') || data_get($p,'tbc_rujuk')) ? '<span class="text-red-600">RUJUK!</span>' : '<span class="text-green-600">Aman</span>' !!}
                        </td>
                        <td class="text-center">
                            <div class="flex items-center justify-center gap-2">
                                <button onclick="editDariDetail({{ $warga->id }}, {{ $p['id'] }})"
                                        class="btn btn-sm btn-warning">Edit</button>

                                <button onclick="hapusDariDetail({{ $p['id'] }})"
                                        class="btn btn-sm btn-error">Hapus</button>

                                <button onclick="openDetail({{ $p['id'] }})"
                                        class="btn btn-sm btn-outline ml-1">Detail</button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="mt-6 text-right">
        <button class="btn btn-ghost" onclick="document.getElementById('dewasaModal').close()">
            Tutup
        </button>
    </div>
</div>

{{-- Modal per-periksa --}}
<dialog id="periksaDetailModal" class="modal">
    <div class="modal-box w-11/12 max-w-4xl">
        <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2" onclick="closePeriksaDetail()">
            ✕
        </button>
        <div id="periksaDetailBody" class="space-y-4"></div>
        <div class="mt-4 text-right">
            <button class="btn btn-ghost" onclick="closePeriksaDetail()">Tutup</button>
        </div>
    </div>
</dialog>

<script>
    /**
     * window.riwayatMap
     * - buat peta periksaId => periksaObject
     * - pastikan tiap periksa sudah membawa sub-key 'warga' (id, nama, nik, usia)
     */
    window.riwayatMap = @json(collect($riwayat)->mapWithKeys(function($r){
        // jika $r sudah array (kita pada controller mem-map ke array), gunakan langsung
        if (is_array($r)) {
            return [$r['id'] => $r];
        }
        // else model instance
        $arr = $r->toArray();
        return [$r->id => $arr];
    }));

    // helper kecil
    function fmt(v){ return (v === null || v === undefined || v === '') ? '-' : v; }

    // RENDER DETAIL PER PERIKSA — lengkap & tergrup rapi
    function renderPeriksaDetail(periksa) {
        const warga = periksa.warga || {};
        // derive beberapa label jika belum ada
        const merokokLabel = periksa.merokok_label ?? (periksa.merokok === 0 ? 'Tidak' : (periksa.merokok === 1 ? '<20' : (periksa.merokok === 2 ? '20–39' : (periksa.merokok === 3 ? '≥40' : periksa.merokok))));
        const skorPuma = periksa.skor_puma ?? 0;

        // build HTML (tailwind + readable groups)
        return `
            <div class="prose max-w-none">
                <!-- HEADER -->
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-xl font-bold text-indigo-700">Detail Pemeriksaan — ${fmt(periksa.tanggal_periksa_formatted ?? periksa.tanggal_periksa)}</h3>
                        <p class="text-sm text-gray-500">${fmt(warga.nama ?? periksa.nama)} — NIK: ${fmt(warga.nik ?? periksa.nik)}</p>
                    </div>
                    <div class="text-right text-sm text-gray-600">
                        <div>Periksa ID: <span class="font-mono">${fmt(periksa.id)}</span></div>
                        <div>Petugas: <span class="font-medium">${fmt(periksa.petugas_name ?? periksa.petugas ?? '-')}</span></div>
                    </div>
                </div>

                <!-- GROUP: Profil & Antropometri -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div class="bg-gray-50 p-4 rounded-lg border">
                        <h4 class="font-bold text-lg text-indigo-700">Profil</h4>
                        <dl class="mt-2 text-sm space-y-2">
                            <div class="flex justify-between"><dt class="font-semibold">Nama</dt><dd>${fmt(warga.nama ?? periksa.nama)}</dd></div>
                            <div class="flex justify-between"><dt class="font-semibold">NIK</dt><dd>${fmt(warga.nik ?? periksa.nik)}</dd></div>
                            <div class="flex justify-between"><dt class="font-semibold">Usia</dt><dd>${fmt(warga.usia ?? periksa.umur)}</dd></div>
                            <div class="flex justify-between"><dt class="font-semibold">Jenis Kelamin</dt><dd>${fmt(periksa.jenis_kelamin)}</dd></div>
                        </dl>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-lg border">
                        <h4 class="font-bold text-lg text-indigo-700">Penimbangan / Antropometri</h4>
                        <dl class="mt-2 text-sm space-y-2">
                            <div class="flex justify-between"><dt class="font-semibold">Berat (kg)</dt><dd>${fmt(periksa.berat_badan)}</dd></div>
                            <div class="flex justify-between"><dt class="font-semibold">Tinggi (cm)</dt><dd>${fmt(periksa.tinggi_badan)}</dd></div>
                            <div class="flex justify-between"><dt class="font-semibold">IMT</dt><dd>${fmt(periksa.imt ?? '-')}</dd></div>
                            <div class="flex justify-between"><dt class="font-semibold">Kategori IMT</dt><dd>${fmt(periksa.kategori_imt ?? '-')}</dd></div>
                            <div class="flex justify-between"><dt class="font-semibold">Lingkar Perut (cm)</dt><dd>${fmt(periksa.lingkar_perut)}</dd></div>
                            <div class="flex justify-between"><dt class="font-semibold">Lingkar Lengan Atas (cm)</dt><dd>${fmt(periksa.lingkar_lengan_atas)}</dd></div>
                        </dl>
                    </div>
                </div>

                <!-- GROUP: Tekanan Darah & Gula -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                    <div class="bg-white p-4 rounded-lg border">
                        <div class="text-sm text-gray-500">Sistole / Diastole</div>
                        <div class="text-2xl font-bold mt-2">${fmt(periksa.sistole)} / ${fmt(periksa.diastole)}</div>
                    </div>
                    <div class="bg-white p-4 rounded-lg border">
                        <div class="text-sm text-gray-500">Hasil Tekanan Darah</div>
                        <div class="text-xl font-bold mt-2 ${ (periksa.td_kategori || '').toString().toLowerCase().includes('tinggi') ? 'text-red-600' : '' }">${fmt(periksa.td_kategori ?? periksa.td_hasil ?? '-')}</div>
                    </div>
                    <div class="bg-white p-4 rounded-lg border">
                        <div class="text-sm text-gray-500">Gula Darah (mg/dL)</div>
                        <div class="text-xl font-bold mt-2 ${ (periksa.gula_kategori || '').toString().toLowerCase().includes('t') ? 'text-red-600' : '' }">${fmt(periksa.gula_darah)} mg/dL — ${fmt(periksa.gula_kategori ?? periksa.gula_hasil ?? '-')}</div>
                    </div>
                </div>

                <!-- GROUP: Tes Sensori (Mata & Telinga) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div class="bg-white p-4 rounded-lg border">
                        <h4 class="font-semibold text-indigo-700">Tes Mata</h4>
                        <div class="flex gap-6 mt-3">
                            <div class="text-center">
                                <div class="text-xs text-gray-500">Mata Kanan</div>
                                <div class="text-lg font-bold">${fmt(periksa.mata_kanan ?? 'Normal')}</div>
                            </div>
                            <div class="text-center">
                                <div class="text-xs text-gray-500">Mata Kiri</div>
                                <div class="text-lg font-bold">${fmt(periksa.mata_kiri ?? 'Normal')}</div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-4 rounded-lg border">
                        <h4 class="font-semibold text-indigo-700">Tes Telinga (Bisik)</h4>
                        <div class="flex gap-6 mt-3">
                            <div class="text-center">
                                <div class="text-xs text-gray-500">Telinga Kanan</div>
                                <div class="text-lg font-bold">${fmt(periksa.telinga_kanan ?? 'Normal')}</div>
                            </div>
                            <div class="text-center">
                                <div class="text-xs text-gray-500">Telinga Kiri</div>
                                <div class="text-lg font-bold">${fmt(periksa.telinga_kiri ?? 'Normal')}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- GROUP: PUMA (detail pertanyaan + bobot + total) -->
                <div class="bg-orange-50 p-4 rounded-lg border mt-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="font-bold text-orange-700">Kuesioner PUMA (Skrining PPOK)</h4>
                            <div class="text-sm text-gray-500">Skor ≥6 direkomendasikan rujuk</div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm text-gray-500">Merokok</div>
                            <div class="font-bold">${fmt(merokokLabel)}</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-4">
                        <div class="p-3 rounded border bg-white">
                            <div class="text-sm text-gray-600">Napas pendek saat berjalan cepat</div>
                            <div class="font-medium mt-1">${periksa.puma_napas_pendek ? 'Ya' : 'Tidak'} ${periksa.puma_napas_pendek_weight ? `<span class="text-xs text-gray-500">(${periksa.puma_napas_pendek_weight})</span>` : ''}</div>
                        </div>
                        <div class="p-3 rounded border bg-white">
                            <div class="text-sm text-gray-600">Dahak kronis / sulit mengeluarkan dahak</div>
                            <div class="font-medium mt-1">${periksa.puma_dahak ? 'Ya' : 'Tidak'} ${periksa.puma_dahak_weight ? `<span class="text-xs text-gray-500">(${periksa.puma_dahak_weight})</span>` : ''}</div>
                        </div>
                        <div class="p-3 rounded border bg-white">
                            <div class="text-sm text-gray-600">Batuk kronis (bukan karena flu)</div>
                            <div class="font-medium mt-1">${periksa.puma_batuk ? 'Ya' : 'Tidak'} ${periksa.puma_batuk_weight ? `<span class="text-xs text-gray-500">(${periksa.puma_batuk_weight})</span>` : ''}</div>
                        </div>
                        <div class="p-3 rounded border bg-white">
                            <div class="text-sm text-gray-600">Pernah diminta spirometri / peakflow</div>
                            <div class="font-medium mt-1">${periksa.puma_tes_paru ? 'Ya' : 'Tidak'} ${periksa.puma_tes_paru_weight ? `<span class="text-xs text-gray-500">(${periksa.puma_tes_paru_weight})</span>` : ''}</div>
                        </div>
                    </div>

                    <div class="mt-4 text-right">
                        <div class="text-sm text-gray-500">Usia Skor: <span class="font-semibold">${fmt(periksa.usia_puma ?? periksa.usia ?? '-')}</span></div>
                        <div class="text-2xl font-bold mt-1 ${skorPuma >= 6 ? 'text-red-600' : 'text-green-600'}">Total Skor: ${skorPuma}</div>
                    </div>
                </div>

                <!-- GROUP: TBC -->
                <div class="bg-red-50 p-4 rounded-lg border mt-4">
                    <h4 class="font-bold text-red-700">Skrining Gejala TBC</h4>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mt-3">
                        <div class="p-3 border rounded text-center">
                            <div class="text-xs text-gray-500">Batuk ≥2 minggu</div>
                            <div class="font-bold ${ (periksa.tbc_batuk === 'Ya') ? 'text-red-600' : '' }">${fmt(periksa.tbc_batuk ?? 'Tidak')}</div>
                        </div>
                        <div class="p-3 border rounded text-center">
                            <div class="text-xs text-gray-500">Demam ≥2 minggu</div>
                            <div class="font-bold ${ (periksa.tbc_demam === 'Ya') ? 'text-red-600' : '' }">${fmt(periksa.tbc_demam ?? 'Tidak')}</div>
                        </div>
                        <div class="p-3 border rounded text-center">
                            <div class="text-xs text-gray-500">BB turun 2 bulan</div>
                            <div class="font-bold ${ (periksa.tbc_bb_turun === 'Ya') ? 'text-red-600' : '' }">${fmt(periksa.tbc_bb_turun ?? 'Tidak')}</div>
                        </div>
                        <div class="p-3 border rounded text-center">
                            <div class="text-xs text-gray-500">Kontak erat TBC</div>
                            <div class="font-bold ${ (periksa.tbc_kontak_erat === 'Ya') ? 'text-red-600' : '' }">${fmt(periksa.tbc_kontak_erat ?? 'Tidak')}</div>
                        </div>
                    </div>
                    <div class="mt-3"><strong>Kesimpulan:</strong> <span class="font-bold">${periksa.tbc_rujuk ? 'Suspek TBC — Rujuk' : 'Tidak ada gejala'}</span></div>
                </div>

                <!-- GROUP: Edukasi, Kontrasepsi, Catatan -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div class="bg-white p-4 rounded-lg border">
                        <h4 class="font-semibold text-indigo-700">Edukasi & Kontrasepsi</h4>
                        <div class="mt-2 text-sm">
                            <div class="flex justify-between"><dt class="font-semibold">Wawancara Kontrasepsi</dt><dd>${fmt(periksa.wawancara_kontrasepsi)}</dd></div>
                            <div class="flex justify-between mt-2"><dt class="font-semibold">Jenis Kontrasepsi</dt><dd>${fmt(periksa.jenis_kontrasepsi)}</dd></div>
                            <div class="mt-3"><dt class="font-semibold">Edukasi</dt><dd class="mt-1">${fmt(periksa.edukasi)}</dd></div>
                        </div>
                    </div>

                    <div class="bg-white p-4 rounded-lg border">
                        <h4 class="font-semibold text-indigo-700">Catatan & Rujukan</h4>
                        <div class="mt-2 text-sm space-y-2">
                            <div class="flex justify-between"><dt class="font-semibold">Catatan</dt><dd>${fmt(periksa.catatan)}</dd></div>
                            <div class="flex justify-between"><dt class="font-semibold">Rujuk Manual</dt><dd class="${periksa.rujuk_puskesmas ? 'text-red-600 font-bold' : 'text-gray-700'}">${periksa.rujuk_puskesmas ? 'Ya (Manual)' : 'Tidak'}</dd></div>
                        </div>
                    </div>
                </div>

                <!-- GROUP: Rujuk alasan jika ada -->
                <div class="bg-white p-4 rounded-lg border mt-4">
                    <h4 class="font-semibold text-indigo-700">Alasan Rujukan (jika ada)</h4>
                    <div class="mt-2 text-sm">
                        ${(() => {
                            const reasons = [];
                            if ((periksa.sistole ?? 0) >= 140 || (periksa.diastole ?? 0) >= 90) reasons.push('TD Tinggi');
                            if ((periksa.gula_darah ?? 0) > 200) reasons.push('Gula Darah Tinggi');
                            if ((periksa.skor_puma ?? 0) >= 6) reasons.push('Skor PUMA ≥6');
                            const tbcCount = ['tbc_batuk','tbc_demam','tbc_bb_turun','tbc_kontak_erat'].reduce((s,k)=> s + ((periksa[k] === 'Ya')?1:0), 0);
                            if (tbcCount >= 2) reasons.push('Suspek TBC');
                            if (reasons.length === 0) return '<div class="text-green-600 font-bold">Tidak ada rujukan</div>';
                            return `<div class="text-red-600 font-bold">${reasons.join(', ')}</div>`;
                        })()}
                    </div>
                </div>

                <!-- ACTION: Edit / Hapus -->
                <div class="flex items-center justify-end gap-2 mt-3">
                    <button onclick="editDariDetail(${ (periksa.warga && periksa.warga.id) ? periksa.warga.id : periksa.warga_id }, ${periksa.id})" class="btn btn-warning btn-sm">Edit</button>
                    <button onclick="hapusDariDetail(${periksa.id})" class="btn btn-error btn-sm">Hapus</button>
                </div>
            </div>
        `;
    }
    function openDetail(periksaId) {
        const periksa = window.riwayatMap[periksaId];
        if (!periksa) {
            Swal.fire('Data Tidak Ditemukan', 'Mohon buka Riwayat lewat tombol Detail pada halaman utama agar data riwayat dimuat.', 'warning');
            return;
        }

        // normalize boolean-like fields (optional)
        periksa.tanggal_periksa_formatted = periksa.tanggal_periksa ? (new Date(periksa.tanggal_periksa)).toLocaleDateString() : periksa.tanggal_periksa;

        const body = document.getElementById('periksaDetailBody');
        body.innerHTML = renderPeriksaDetail(periksa);

        const dlg = document.getElementById('periksaDetailModal');
        if (dlg.showModal) dlg.showModal(); else dlg.classList.add('modal-open');
    }

    function closePeriksaDetail() {
        const dlg = document.getElementById('periksaDetailModal');
        const body = document.getElementById('periksaDetailBody');
        body.innerHTML = '';
        if (dlg.close) dlg.close(); else dlg.classList.remove('modal-open');
    }
</script>


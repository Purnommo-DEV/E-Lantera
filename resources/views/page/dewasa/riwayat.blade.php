{{-- resources/views/page/dewasa/riwayat.blade.php --}}
<div class="p-6">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h3 class="text-2xl font-bold text-indigo-700">{{ $warga->nama }}</h3>
            <p class="text-gray-600">NIK: {{ $warga->nik }} • Usia: {{ $warga->usia }} tahun</p>
        </div>
        <button onclick="bukaFormTambah({{ $warga->id }})"
                class="btn btn-success btn-lg">
            + Periksa Baru
        </button>
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
                        <td>{{ $p->tanggal_periksa->format('d/m/Y') }}</td>
                        <td class="text-center">
                            {!! $p->imt_badge_html ?? '<span class="text-gray-400">-</span>' !!}
                        </td>
                        <td class="text-center">
                            {!! $p->td_badge_html ?? '<span class="text-gray-400">-</span>' !!}
                        </td>
                        <td class="text-center">{{ $p->gula_kategori == 'T' ? '<span class="text-red-600 font-bold">Tinggi</span>' : 'Normal' }}</td>
                        <td class="text-center font-bold {{ $p->skor_puma >= 6 ? 'text-red-600' : '' }}">{{ $p->skor_puma }}</td>
                        <td class="text-center">{!! $p->tbc_rujuk ? '<span class="text-red-600 font-bold">YA</span>' : '-' !!}</td>
                        <td class="text-center font-bold text-lg">
                            {!! $p->rujuk_puskesmas || $p->tbc_rujuk ? '<span class="text-red-600">RUJUK!</span>' : '<span class="text-green-600">Aman</span>' !!}
                        </td>
                        <td class="text-center">
                            <button onclick="editDariDetail({{ $warga->id }}, {{ $p->id }})"
                                    class="btn btn-sm btn-warning">Edit</button>
                            <button onclick="hapusDariDetail({{ $p->id }})"
                                    class="btn btn-sm btn-error ml-2">Hapus</button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="mt-8 text-right">
        <button class="btn btn-ghost" onclick="document.getElementById('dewasaModal').close()">
            Tutup
        </button>
    </div>
</div>
{{-- resources/views/page/lansia/riwayat.blade.php --}}

<div class="space-y-4">
    {{-- Identitas singkat --}}
    <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 flex flex-col sm:flex-row justify-between gap-4">
        <div>
            <div class="text-sm text-gray-500">Nama</div>
            <div class="text-lg font-bold text-emerald-800">{{ $warga->nama }}</div>
            <div class="text-sm text-gray-500 mt-1">NIK: {{ $warga->nik }}</div>
        </div>
        <div class="text-sm text-right text-gray-600">
            <div>Tanggal lahir: {{ $warga->tanggal_lahir?->format('d-m-Y') ?? '-' }}</div>
            <div>Usia: {{ $warga->tanggal_lahir ? \Carbon\Carbon::parse($warga->tanggal_lahir)->age.' tahun' : '-' }}</div>
        </div>
    </div>

    {{-- Tabel riwayat --}}
    <div class="overflow-x-auto">
        <table class="table table-zebra w-full">
            <thead class="bg-emerald-100 text-emerald-900">
                <tr>
                    <th>#</th>
                    <th>Tanggal Periksa</th>
                    <th>AKS Total</th>
                    <th>AKS Kategori</th>
                    <th>SKILAS (+)</th>
                    <th>Perlu Rujuk?</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse($periksas as $i => $p)
                @php
                    $skilasPositif = collect($p->getAttributes())
                        ->filter(function ($value, $key) {
                            return str_starts_with($key, 'skil_')
                                && !in_array($key, ['skil_rujuk_otomatis','skil_rujuk_manual','skil_edukasi','skil_catatan'])
                                && $value == 1;
                        })->count();

                    $perluRujuk = $p->aks_perlu_rujuk || $p->skil_rujuk_otomatis || $p->skil_rujuk_manual;
                @endphp
                <tr>
                    <td>{{ $i+1 }}</td>
                    <td>{{ \Carbon\Carbon::parse($p->tanggal_periksa)->format('d/m/Y') }}</td>
                    <td class="text-center font-bold">{{ $p->aks_total_skor ?? '-' }}</td>
                    <td class="text-center">{{ $p->aks_kategori ?? '-' }}</td>
                    <td class="text-center">
                        @if($skilasPositif > 0)
                            <span class="text-red-600 font-bold">+{{ $skilasPositif }}</span>
                        @else
                            <span class="text-gray-500">-</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($perluRujuk)
                            <span class="badge badge-error badge-lg">RUJUK</span>
                        @else
                            <span class="badge badge-success badge-lg">Aman</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <button
                            type="button"
                            class="btn btn-sm btn-primary btn-edit-periksa"
                            data-url="{{ route('lansia.edit', [$warga->id, $p->id]) }}"
                        >
                            Edit
                        </button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-gray-500 py-4">
                        Belum ada riwayat pemeriksaan.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="flex justify-end mt-4">
        <button type="button"
                class="btn btn-ghost"
                onclick="tutupModal()">
            Tutup
        </button>
    </div>
</div>

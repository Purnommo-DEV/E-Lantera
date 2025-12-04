<div class="grid grid-cols-1 gap-8">
    <!-- Dewasa -->
    <div>
        <h4 class="text-2xl font-bold text-emerald-700 mb-4">Pemeriksaan Dewasa & Lansia (≥15 th)</h4>
        @if($dewasa->count())
            <div class="overflow-x-auto">
                <table class="table table-compact w-full">
                    <thead>
                        <tr class="bg-emerald-100">
                            <th>Tanggal</th>
                            <th>NIK</th>
                            <th>Nama</th>
                            <th>IMT</th>
                            <th>TD</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($dewasa as $d)
                            <tr>
                                <td>{{ optional(\Carbon\Carbon::parse($d->tanggal_periksa))->format('d/m/Y') }}</td>
                                <td>{{ $d->warga->nik }}</td>
                                <td>{{ $d->warga->nama }}</td>
                                <td>{!! $d->imt_badge_html ?? '-' !!}</td>
                                <td>{!! $d->td_badge_html ?? '-' !!}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-gray-500 italic">Tidak ada data dewasa/lansia</p>
        @endif
    </div>

    <!-- Lansia AKS/SKILAS -->
    <div>
        <h4 class="text-2xl font-bold text-indigo-700 mb-4">Pemeriksaan Lansia (AKS & SKILAS)</h4>
        @if($lansia->count())
            <div class="overflow-x-auto">
                <table class="table table-compact w-full">
                    <thead>
                        <tr class="bg-indigo-100">
                            <th>Tanggal</th>
                            <th>NIK</th>
                            <th>Nama</th>
                            <th>AKS</th>
                            <th>SKILAS (+)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lansia as $l)
                            <tr>
                                <td>{{ optional(\Carbon\Carbon::parse($l->tanggal_periksa))->format('d/m/Y') }}</td>
                                <td>{{ $l->warga->nik }}</td>
                                <td>{{ $l->warga->nama }}</td>
                                <td>
                                    <span class="badge badge-sm {{ $l->aks_kategori == 'M' ? 'badge-success' : 'badge-warning' }}">
                                        {{ $l->aks_kategori_text }}
                                    </span>
                                </td>
                                <td>{{ $l->skilas_positif ? 'Ya' : 'Tidak' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-gray-500 italic">Tidak ada data lansia</p>
        @endif
    </div>
</div>

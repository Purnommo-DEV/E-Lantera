@extends('layouts.app')
@section('title', 'Register Dewasa & Lansia')

@section('content')
<div class="bg-white rounded-2xl shadow-xl overflow-hidden">

    {{-- ================= HEADER ================= --}}
    <div class="bg-gradient-to-r from-emerald-700 to-emerald-900 text-white p-4 md:p-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">

            <div>
                <h3 class="text-2xl md:text-3xl font-bold">
                    Register Dewasa & Lansia
                </h3>
                <p class="opacity-90 mt-1 text-sm">
                    Posyandu Taman Cipulir Estate – RW 08
                </p>
            </div>

            {{-- FILTER --}}
            <form method="GET" class="flex flex-wrap items-center gap-2">

                {{-- SEARCH --}}
                <div class="relative">
                    <input
                        type="text"
                        id="searchInput"
                        autocomplete="off"
                        onkeydown="if(event.key==='Enter') event.preventDefault()"
                        placeholder="Cari nama / JK / umur..."
                        class="input input-bordered bg-white text-gray-800 w-56"
                    />
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">
                        🔍
                    </span>
                </div>

                <button type="button"
                    onclick="searchInput.value='';searchInput.dispatchEvent(new Event('input'));"
                    class="btn btn-ghost btn-sm">
                    Reset
                </button>

                {{-- TAHUN --}}
                <select name="tahun"
                    class="select select-bordered bg-white text-gray-800 min-w-[120px]">
                    @for($y = now()->year; $y >= now()->year - 5; $y--)
                        <option value="{{ $y }}" @selected($tahun == $y)>
                            {{ $y }}
                        </option>
                    @endfor
                </select>

                <button class="btn bg-amber-400 text-emerald-950">
                    Tampilkan
                </button>
            </form>

        </div>
    </div>

    {{-- ================= SUMMARY ================= --}}
    @php
        $totalTidakAdaNik = $warga->filter(fn($w) => !$w->nik)->count();
        $totalTidakAdaTgl = $warga->filter(fn($w) => !$w->tanggal_lahir)->count();
        $totalLansia = $warga->filter(fn($w) =>
            $w->tanggal_lahir && \Carbon\Carbon::parse($w->tanggal_lahir)->age >= 60
        )->count();
    @endphp

    <div class="p-4 md:p-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="rounded-xl p-4 font-semibold" style="background:#92d050">
            Tidak ada NIK
            <div class="text-2xl font-bold">{{ $totalTidakAdaNik }}</div>
        </div>

        <div class="rounded-xl p-4 font-semibold" style="background:#ffc000">
            Tidak ada Tgl Lahir
            <div class="text-2xl font-bold">{{ $totalTidakAdaTgl }}</div>
        </div>

        <div class="rounded-xl p-4 font-semibold" style="background:#9bc2e6">
            Lansia &gt; 60 Tahun
            <div class="text-2xl font-bold">{{ $totalLansia }}</div>
        </div>
    </div>

    {{-- ================= TABLE ================= --}}
    <div class="p-4 md:p-6 pt-0">
        <div class="overflow-x-auto">

            <table class="register-table w-full text-xs">

                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama</th>
                        <th>JK</th>
                        <th>Tgl Lahir</th>
                        <th>Umur</th>
                        @foreach(['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'] as $bulan)
                            <th class="text-center">{{ $bulan }}</th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @foreach($warga as $i => $w)
                        @php
                            $umur = $w->tanggal_lahir
                                ? \Carbon\Carbon::parse($w->tanggal_lahir)->age
                                : null;
                        @endphp

                        <tr
                            data-nama="{{ strtolower($w->nama) }}"
                            data-jk="{{ strtolower($w->jenis_kelamin) }}"
                            data-umur="{{ $umur ?? '' }}"
                        >
                            {{-- NO (PENANDA LANSIA SAJA) --}}
                            <td class="text-center font-bold"
                                @if($umur !== null && $umur >= 60)
                                    style="background-color: #9bc2e6!important;"
                                @endif
                            >
                                {{ $i + 1 }}
                            </td>

                            <td class="font-bold">{{ $w->nama }}</td>
                            <td class="text-center">{{ $w->jenis_kelamin[0] }}</td>
                            <td
                                @if(!$w->tanggal_lahir)
                                    style="background-color:#ffc000!important; font-weight:bold;"
                                @endif
                            >
                                {{ $w->tanggal_lahir ? \Carbon\Carbon::parse($w->tanggal_lahir)->format('d-m-Y') : '-' }}
                            </td>
                            <td class="text-center font-bold">
                                {{ $umur !== null ? $umur.' tahun' : '-' }}
                            </td>

                            {{-- BULAN (TANPA WARNA LANSIA) --}}
                            @for($b = 1; $b <= 12; $b++)
                                @php
                                    $bg = '';
                                    if (!$w->nik) {
                                        $bg = '#92d050';
                                    }
                                @endphp

                                <td class="text-center font-semibold"
                                    style="background-color:{{ $bg }}">
                                    @if($w->hadir[$b] ?? false)
                                        ✔
                                    @endif
                                </td>
                            @endfor
                        </tr>
                    @endforeach
                </tbody>

            </table>
        </div>
    </div>

</div>
@endsection

{{-- ================= STYLE ================= --}}
@push('styles')
<style>
    .register-table {
        border-collapse: collapse;
    }

    .register-table th,
    .register-table td {
        border: 1px solid #d1d5db;
        padding: 6px 8px;
        vertical-align: middle;
    }

    .register-table thead th {
        background: #ecfdf5;
        color: #065f46;
        font-weight: 600;
        text-align: center;
    }

    .register-table tbody tr:hover td {
        background-color: #f9fafb;
    }

    /* Kolom non-bulan tetap putih */
    .register-table tbody td:nth-child(-n+5) {
        background-color: #ffffff !important;
    }
</style>
@endpush

{{-- ================= SCRIPT ================= --}}
@push('scripts')
<script>
    const searchInput = document.getElementById('searchInput');
    const rows = document.querySelectorAll('.register-table tbody tr');

    searchInput.addEventListener('input', function () {
        const keyword = this.value.toLowerCase().trim();

        rows.forEach(row => {
            const nama = row.dataset.nama || '';
            const jk   = row.dataset.jk || '';
            const umur = row.dataset.umur || '';

            const match =
                nama.includes(keyword) ||
                jk.includes(keyword) ||
                umur.includes(keyword);

            row.style.display = match ? '' : 'none';
        });
    });
</script>
@endpush

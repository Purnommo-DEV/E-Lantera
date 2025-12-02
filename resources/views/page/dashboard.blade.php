@extends('layouts.app')

@section('title', 'Dashboard')

@push('styles')
<style>
    /* sedikit shadow + rounded agar terasa "modern" */
    .stat-card {
        border-radius: 0.75rem;
        box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
    }
    /* icon container */
    .stat-icon {
        width: 3.25rem;
        height: 3.25rem;
        display: grid;
        place-items: center;
        border-radius: 0.75rem;
        background: rgba(255,255,255,0.08);
    }
    /* card header kecil */
    .muted { color: #6b7280; } /* text-gray-500 */
</style>
@endpush

@section('content')
<div class="space-y-6">

    {{-- TOP STATS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        {{-- Total Users --}}
        <div class="stat-card bg-white p-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-gray-500">Total Pengguna</p>
                    <h2 class="text-2xl md:text-3xl font-bold text-slate-800 mt-1">{{ number_format($totalUsers) }}</h2>
                    <p class="text-xs muted mt-2">Akun terdaftar di sistem</p>
                </div>
                <div class="stat-icon bg-gradient-to-br from-sky-500 to-indigo-600 text-white">
                    {{-- heroicon users --}}
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87M16 11a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
            </div>

            <div class="mt-4 flex items-center justify-between">
                <a href="{{ route('user.index') }}" class="text-sm text-indigo-600 hover:underline">Kelola Pengguna</a>
                <span class="text-xs muted">Updated: <strong class="text-gray-700">{{ now()->translatedFormat('d M Y') }}</strong></span>
            </div>
        </div>

        {{-- Roles --}}
        <div class="stat-card bg-white p-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-gray-500">Role & Permission</p>
                    <h2 class="text-2xl md:text-3xl font-bold text-slate-800 mt-1">{{ number_format($totalRoles) }}</h2>
                    <p class="text-xs muted mt-2">Hirarki & akses pengguna</p>
                </div>
                <div class="stat-icon bg-gradient-to-br from-emerald-500 to-teal-600 text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 14l9-5-9-5-9 5 9 5z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 14l6.16-3.422A12.083 12.083 0 0118 20.128V20a2 2 0 01-2 2H8a2 2 0 01-2-2v-.128a12.083 12.083 0 00-0.16-9.55L12 14z" />
                    </svg>
                </div>
            </div>

            <div class="mt-4 flex items-center justify-between">
                <a href="{{ route('role.index') }}" class="text-sm text-indigo-600 hover:underline">Kelola Role</a>
                <span class="text-xs muted">Permissions aktif</span>
            </div>
        </div>

        {{-- Total Warga --}}
        <div class="stat-card bg-white p-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-gray-500">Jumlah Warga</p>
                    <h2 class="text-2xl md:text-3xl font-bold text-slate-800 mt-1">{{ number_format($totalWarga) }}</h2>
                    <p class="text-xs muted mt-2">Data Keluarga & personal</p>
                </div>
                <div class="stat-icon bg-gradient-to-br from-yellow-400 to-orange-500 text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                       <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5.121 17.804A13.937 13.937 0 0112 15c2.21 0 4.288.5 6.121 1.804M12 12a4 4 0 100-8 4 4 0 000 8z" />
                    </svg>
                </div>
            </div>

            <div class="mt-4 flex items-center justify-between">
                <a href="{{ route('warga.index') ?? '#' }}" class="text-sm text-indigo-600 hover:underline">Lihat Data Warga</a>
                <span class="text-xs muted">Terdaftar</span>
            </div>
        </div>

        {{-- Pemeriksaan Bulan Ini --}}
        <div class="stat-card bg-white p-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-gray-500">Pemeriksaan (Bulan ini)</p>
                    <h2 class="text-2xl md:text-3xl font-bold text-slate-800 mt-1">{{ number_format($dewasaThisMonth + $lansiaThisMonth) }}</h2>
                    <div class="mt-2 text-sm text-gray-600">
                        <div>Dewasa: <strong>{{ number_format($dewasaThisMonth) }}</strong></div>
                        <div>Lansia: <strong>{{ number_format($lansiaThisMonth) }}</strong></div>
                    </div>
                </div>
                <div class="stat-icon bg-gradient-to-br from-rose-500 to-red-600 text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-2.21 0-4 .895-4 2s1.79 2 4 2 4-.895 4-2-1.79-2-4-2z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18v-2a6 6 0 0112 0v2" />
                    </svg>
                </div>
            </div>

            <div class="mt-4 flex items-center justify-between">
                <a href="{{ route('rekap.bulanan') }}" class="text-sm text-indigo-600 hover:underline">Lihat Rekap Bulanan</a>
                <span class="text-xs muted">Data bulan ini</span>
            </div>
        </div>
    </div>

    {{-- SECOND ROW: cards --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Rujukan --}}
        <div class="bg-white p-6 rounded-lg shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-slate-800">Rujukan Bulan Ini</h3>
                    <p class="mt-1 text-3xl font-bold text-rose-600">{{ number_format($totalRujukanThisMonth) }}</p>
                    <p class="text-sm muted mt-2">Gabungan rujukan dari tabel Dewasa & Lansia.</p>
                </div>
                <div>
                    <a href="{{ route('rekap.bulanan') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-rose-50 text-rose-600 rounded-md shadow-sm hover:bg-rose-100">
                        Detail
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M7 7l3-3 3 3m0 6l-3 3-3-3" /></svg>
                    </a>
                </div>
            </div>
        </div>

        {{-- Aktivitas Terakhir --}}
        <div class="bg-white p-6 rounded-lg shadow-sm">
            <h3 class="text-lg font-semibold text-slate-800">Aktivitas Terakhir</h3>
            @if($lastActivity)
                <div class="mt-3">
                    <div class="text-sm muted">Jenis</div>
                    <div class="font-medium text-slate-700">{{ $lastActivity['type'] }}</div>

                    <div class="mt-2 text-sm muted">Waktu</div>
                    <div class="font-medium text-slate-700">{{ \Carbon\Carbon::parse($lastActivity['time'])->translatedFormat('d M Y H:i') }}</div>

                    <div class="mt-4">
                        {{-- ganti routes jika berbeda --}}
                        @php
                            $link = $lastActivity['type'] === 'Dewasa'
                                ? (route('dewasa.data', $lastActivity['id'] ?? '') ?? '#')
                                : (route('lansia.data', $lastActivity['id'] ?? '') ?? '#');
                        @endphp

                        <a href="#" onclick="window.open('{{ $link }}', '_blank')" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-md hover:shadow-md">
                            Buka Detail Pemeriksaan
                        </a>
                    </div>
                </div>
            @else
                <div class="mt-3 text-sm muted">Belum ada aktivitas pemeriksaan.</div>
            @endif
        </div>

        {{-- Grafik 12 bulan --}}
        <div class="bg-white p-6 rounded-lg shadow-sm">
            <h3 class="text-lg font-semibold text-slate-800">Grafik 12 Bulan</h3>

            <!-- WRAPPER DENGAN HEIGHT TETAP -->
            <div id="chart12Wrapper" class="mt-4 w-full" style="height:240px;">
                <canvas id="chart12" style="width:100%; height:100%; display:block;"></canvas>
            </div>

            <p class="mt-3 text-sm muted">Ringkasan jumlah pemeriksaan per bulan (Dewasa vs Lansia).</p>
        </div>

    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // pastikan ada data
        const months = @json($months->reverse()->values());
        const labels = months.map(m => m.label);
        const dewasa = months.map(m => m.dewasa);
        const lansia = months.map(m => m.lansia);

        // ambil elemen
        const canvas = document.getElementById('chart12');
        const ctx = canvas.getContext('2d');

        // jika chart sebelumnya ada (mis. PJAX), hentikan dulu
        if (window._chart12Instance) {
            try { window._chart12Instance.destroy(); } catch(e){ /* ignore */ }
            window._chart12Instance = null;
        }

        // gradient (opsional)
        const gradientA = ctx.createLinearGradient(0,0,0,240);
        gradientA.addColorStop(0, 'rgba(99,102,241,0.85)');
        gradientA.addColorStop(1, 'rgba(99,102,241,0.35)');

        const gradientB = ctx.createLinearGradient(0,0,0,240);
        gradientB.addColorStop(0, 'rgba(16,185,129,0.85)');
        gradientB.addColorStop(1, 'rgba(16,185,129,0.35)');

        // buat chart — NOTE: maintainAspectRatio:false + parent punya height (style di wrapper)
        window._chart12Instance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Dewasa',
                        data: dewasa,
                        backgroundColor: gradientB,
                        borderRadius: 6,
                        barThickness: 18
                    },
                    {
                        label: 'Lansia',
                        data: lansia,
                        backgroundColor: gradientA,
                        borderRadius: 6,
                        barThickness: 18
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: { mode: 'index', intersect: false }
                },
                scales: {
                    x: { stacked: false, grid: { display: false } },
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });

        // Jika ingin chart responsif terhadap perubahan tinggi wrapper, kamu bisa memanggil resize:
        // window.addEventListener('resize', () => window._chart12Instance?.resize());
    });
</script>
@endpush

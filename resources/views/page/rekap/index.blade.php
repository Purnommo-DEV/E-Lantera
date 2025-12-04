@extends('layouts.app')
@section('title', 'Rekap Bulanan')

@section('content')
<div class="bg-white rounded-2xl shadow-xl overflow-hidden">
    <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 text-white p-4 md:p-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 md:gap-6">
            <div>
                <h3 class="text-xl md:text-2xl font-bold">Rekap Pemeriksaan Bulanan</h3>
                <p class="opacity-90 mt-1 text-sm md:text-base">POSYANDU TAMAN CIPULIR ESTATE</p>
            </div>
            <div class="text-right">
                <p class="text-lg md:text-2xl font-bold">{{ now()->translatedFormat('F Y') }}</p>
            </div>
        </div>
    </div>
    <div class="p-4 md:p-6">

        {{-- TOMBOL EXPORT UTAMA (compact) --}}
        <div class="flex flex-col lg:flex-row justify-center items-center gap-3 md:gap-6 mb-6">

            {{-- 1. Export Detail Per Bulan --}}
            <button id="btnExportBulanan"
                    class="btn btn-success btn-lg shadow-lg flex items-center gap-3 w-full lg:w-auto opacity-50"
                    disabled>
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 0 01-2 2z"/>
                </svg>
                <span class="text-left">
                    <div class="font-bold">Export Detail Per Bulan</div>
                    <small class="opacity-80">Klik "Lihat Detail" dulu</small>
                </span>
            </button>

            {{-- 2. Export Format Kemenkes Tahap 1 (Tahunan) --}}
            <div class="dropdown dropdown-hover w-full lg:w-auto">
                <div tabindex="0"
                     class="btn btn-error btn-lg shadow-lg flex items-center gap-3 text-white">
                    <svg class="w-7 h-7" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="text-left">
                        <div class="font-bold">Export Format KEMENKES</div>
                        <small class="opacity-90">PWS-KIA Tahap 1 (Wajib Lapor)</small>
                    </span>
                </div>
                <ul tabindex="0" class="dropdown-content menu p-3 shadow bg-base-100 rounded-box w-64 z-50">
                    <li class="menu-title text-success font-semibold text-sm">
                        Pilih Tahun untuk Download:
                    </li>
                    @for($y = now()->year; $y >= 2023; $y--)
                        <li>
                            <a href="{{ route('rekap.tahunan.kemenkes') }}?tahun={{ $y }}"
                               target="_blank"
                               class="flex justify-between items-center py-2 text-sm font-medium hover:bg-success hover:text-white transition">
                                <span>Tahun {{ $y }}</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          stroke-width="2"
                                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                            </a>
                        </li>
                    @endfor
                </ul>
            </div>
        </div>

        {{-- Tabel Rekap 12 Bulan Terakhir --}}
        <div class="text-center mb-4">
            <p class="text-sm md:text-base font-medium text-gray-700">Menampilkan 12 bulan terakhir</p>
        </div>

        <div class="overflow-x-auto rounded-lg border border-gray-200 shadow-sm">
            <table id="rekapTable" class="table table-zebra w-full text-center text-sm">
                <thead class="bg-yellow-100 text-yellow-900">
                    <tr>
                        <th class="w-10">#</th>
                        <th>Bulan</th>
                        <th>Pemeriksaan Dewasa</th>
                        <th>Pemeriksaan Lansia</th>
                        <th>Total</th>
                        <th class="w-28">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

{{-- Modal Detail Bulanan (compact) --}}
<dialog id="detailModal" class="modal">
    <div class="modal-box w-11/12 max-w-6xl p-4 md:p-5">
        <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2" onclick="detailModal.close()">✕</button>
        <h3 class="text-xl md:text-2xl font-bold mb-3 text-yellow-600">
            Detail Pemeriksaan Bulan <span id="modalJudul"></span>
        </h3>
        <div id="detailContent" class="text-sm"></div>
    </div>
</dialog>
@endsection

@push('styles')
<style>
/* Compact Rekap Bulanan tweaks */

/* DataTables wrapper compact */
#rekapTable_wrapper .dataTables_length,
#rekapTable_wrapper .dataTables_filter {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-bottom: 0.5rem;
}
#rekapTable_wrapper .dataTables_length label,
#rekapTable_wrapper .dataTables_filter label {
    margin: 0;
    font-size: 0.78rem;
}
#rekapTable_wrapper .dataTables_length select,
#rekapTable_wrapper .dataTables_filter input {
    font-size: 0.78rem;
    padding: 0.18rem 0.4rem;
    border-radius: 0.35rem;
}

/* table compact */
#rekapTable { font-size: 0.82rem; }
#rekapTable thead th { padding: 7px 8px !important; font-size: 0.82rem !important; }
#rekapTable tbody td { padding: 6px 8px !important; line-height: 1.12 !important; vertical-align: middle; }

/* action buttons small */
#rekapTable .btn, #rekapTable button { font-size: 0.72rem !important; padding: 6px 8px !important; border-radius: 0.35rem !important; }

/* modal compact */
.modal-box.w-11\/12.max-w-6xl { max-width: 980px; padding: 0.8rem; }
.loading.loading-spinner.loading-lg { width: 36px; height: 36px; }

/* responsive */
@media (max-width: 768px) {
    #rekapTable thead th { font-size: 0.72rem; padding: 6px 6px !important; }
    #rekapTable tbody td { font-size: 0.72rem; padding: 6px 6px !important; }
    .btn { font-size: 0.78rem; padding: 0.4rem 0.6rem; }
}
</style>
@endpush

@push('scripts')
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.dataTables.min.css">

<script>
document.addEventListener('DOMContentLoaded', function () {
    let selectedTahun = null;
    let selectedBulan = null;

    const table = $('#rekapTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: '{{ route('rekap.bulanan.data') }}',
        order: [[0, 'desc']],
        columns: [
            { data: null, render: (d, t, r, m) => m.row + 1 },
            { data: 'bulan' },
            { data: 'dewasa', render: d => d || '<span class="text-gray-400">0</span>' },
            { data: 'lansia', render: d => d || '<span class="text-gray-400">0</span>' },
            { data: 'badge' },
            {
                data: null,
                orderable: false,
                render: function (data) {
                    if (data.total === 0) return '<span class="text-gray-400">—</span>';
                    return `
                        <button class="btn btn-sm btn-primary detail-btn"
                                data-tahun="${data.tahun}"
                                data-bulan="${data.bulan_num}">
                            Lihat Detail
                        </button>
                    `;
                }
            }
        ],
        language: {
            search: "Cari Bulan:",
            lengthMenu: "Tampil _MENU_ bulan",
            info: "_START_ - _END_ dari _TOTAL_",
            paginate: { previous: "←", next: "→" },
            emptyTable: "Belum ada data pemeriksaan"
        }
    });

    // Klik "Lihat Detail"
    $(document).on('click', '.detail-btn', function () {
        selectedTahun = $(this).data('tahun');
        selectedBulan = $(this).data('bulan');

        const namaBulan = $(this).closest('tr').find('td:eq(1)').text();
        $('#modalJudul').text(namaBulan);

        // Aktifkan tombol export detail
        $('#btnExportBulanan')
            .prop('disabled', false)
            .removeClass('opacity-50')
            .addClass('hover:scale-105 transition');

        $('#detailContent').html(
            '<div class="text-center py-8">' +
                '<span class="loading loading-spinner loading-lg"></span>' +
            '</div>'
        );

        const url = `{{ route('rekap.bulanan.detail', ['tahun' => 'TAHUN', 'bulan' => 'BULAN']) }}`
            .replace('TAHUN', selectedTahun)
            .replace('BULAN', selectedBulan);

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text())
            .then(html => $('#detailContent').html(html))
            .catch(() => $('#detailContent').html('<div class="text-center text-red-600 py-6">Gagal memuat detail.</div>'));

        const dlg = document.getElementById('detailModal');
        if (dlg.showModal) dlg.showModal(); else dlg.classList.add('modal-open');
    });

    // Export Detail Per Bulan (format Kemenkes tahap 1 per bulan)
    $('#btnExportBulanan').on('click', function () {
        if (!selectedTahun || !selectedBulan) {
            Swal.fire('Pilih bulan dulu!', 'Klik "Lihat Detail" pada salah satu bulan.', 'warning');
            return;
        }
        const url = `{{ route('rekap.bulanan.export') }}?tahun=${selectedTahun}&bulan=${selectedBulan}`;
        window.open(url, '_blank');
    });

    // Safety: close modal fallback
    $(document).on('click', '.modal .btn-ghost', function() {
        const dlg = $(this).closest('dialog')[0];
        if (dlg && dlg.close) dlg.close(); else $(dlg).removeClass('modal-open');
    });
});
</script>
@endpush

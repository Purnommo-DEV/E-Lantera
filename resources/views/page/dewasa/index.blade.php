@extends('layouts.app')
@section('title', 'Pemeriksaan Dewasa & Lansia')

@section('content')
<div class="bg-white rounded-2xl shadow-xl overflow-hidden">
    <div class="bg-gradient-to-r from-teal-700 to-teal-900 text-white p-6 md:p-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 md:gap-6">
            <div>
                <h3 class="text-2xl md:text-3xl font-bold">Pemeriksaan Dewasa & Lansia</h3>
                <p class="opacity-90 mt-1 text-sm md:text-base">RW xx - Dusun Cipulir Estate, Cipadu Jaya, Larangan</p>
            </div>
            <div class="text-right">
                <p class="text-sm opacity-90">Total Warga ≥15 thn: <span id="totalWarga" class="font-bold text-lg md:text-xl">0</span> orang</p>
                <p class="text-sm opacity-90">Belum pernah diperiksa: <span id="belumPeriksa" class="font-bold text-yellow-300 text-lg md:text-xl">0</span> orang</p>
            </div>
        </div>
    </div>

    <div class="p-4 md:p-8">
        <div class="flex justify-end mb-4">
            <a href="{{ route('dewasa.exportSemua') }}"
               class="px-3 py-2 md:px-4 md:py-2 bg-teal-600 text-white rounded-md hover:bg-teal-700 text-sm md:text-base">
                Export Excel Semua
            </a>
        </div>
        <div class="overflow-x-auto">
            <table id="dewasaTable" class="table table-zebra w-full">
                <thead class="bg-teal-100 text-teal-900">
                    <tr>
                        <th>NIK</th>
                        <th>Nama Lengkap</th>
                        <th>Umur</th>
                        <th>Terakhir Diperiksa</th>
                        <th>IMT</th>
                        <th>TD</th>
                        <th>Skor PUMA</th>
                        <th>TBC</th>
                        <th>Status Rujuk</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL DAISYUI -->
<div id="dewasaModal" class="modal">
    <div class="modal-box w-11/12 max-w-5xl bg-white">
        <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2" onclick="tutupModal()">
            X
        </button>
        <h3 class="text-2xl md:text-3xl font-bold mb-4 md:mb-8 text-teal-700" id="modalTitle">
            Pemeriksaan Dewasa & Lansia
        </h3>
        <div id="formContainer" class="overflow-y-auto max-h-[75vh]"></div>
    </div>
</div>
@endsection

@push('styles')
<style>
    /* ===========================
       DataTable wrapper (kept but compacted)
       =========================== */
    #dewasaTable_wrapper .dataTables_length,
    #dewasaTable_wrapper .dataTables_filter {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
    }

    #dewasaTable_wrapper .dataTables_length { float: left; }
    #dewasaTable_wrapper .dataTables_filter { float: right; }

    #dewasaTable_wrapper .dataTables_length label,
    #dewasaTable_wrapper .dataTables_filter label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin: 0;
        font-size: 0.8125rem; /* 13px */
    }

    #dewasaTable_wrapper .dataTables_length select {
        border-radius: 0.375rem;
        padding: 0.12rem 0.35rem;
        font-size: 0.78rem;
    }

    #dewasaTable_wrapper .dataTables_filter input {
        border: 1px solid #d1d5db;
        border-radius: 0.375rem;
        padding: 0.22rem 0.45rem;
        font-size: 0.85rem;
    }

    /* Responsive: stack controls on small screens */
    @media (max-width: 768px) {
        #dewasaTable_wrapper .dataTables_length,
        #dewasaTable_wrapper .dataTables_filter {
            float: none;
            width: 100%;
            justify-content: space-between;
        }

        #dewasaTable_wrapper .dataTables_filter { margin-top: 0.25rem; }
    }

    /* ===========================
       Compact table styling
       =========================== */
    #dewasaTable { font-size: 0.78rem; } /* ~12.5px */

    #dewasaTable thead th {
        padding: 6px 8px !important;
        font-size: 0.78rem !important;
        font-weight: 600;
        white-space: nowrap;
    }

    #dewasaTable tbody td {
        padding: 6px 8px !important;
        line-height: 1.15 !important;
        vertical-align: middle;
    }

    /* center small cells */
    #dewasaTable tbody td.text-center { font-size: 0.78rem !important; }

    /* compact pagination/info */
    #dewasaTable_wrapper .dataTables_paginate,
    #dewasaTable_wrapper .dataTables_info,
    #dewasaTable_wrapper .dataTables_length {
        font-size: 0.78rem !important;
    }

    /* compact paginate buttons */
    #dewasaTable_wrapper .paginate_button {
        padding: 3px 6px !important;
        margin: 0 2px !important;
    }

    /* compact badges/labels inside table */
    #dewasaTable .badge,
    #dewasaTable .badge-sm {
        font-size: 0.68rem !important;
        padding: 3px 6px !important;
    }

    /* shorten wide "Aksi" column */
    #dewasaTable td.whitespace-nowrap {
        max-width: 240px;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* ===========================
       Compact action buttons (override large buttons)
       =========================== */
    #dewasaTable .btn,
    #dewasaTable button,
    #dewasaTable a {
        font-size: 0.72rem !important;
        padding: 6px 10px !important;
        border-radius: 0.375rem !important;
        line-height: 1 !important;
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        white-space: nowrap;
        box-shadow: none !important;
    }

    /* make Periksa/Detail smaller and less tall */
    #dewasaTable .btn[onclick^="bukaFormTambah"],
    #dewasaTable .btn[onclick^="bukaDetail"],
    #dewasaTable .bg-teal-600,
    #dewasaTable .bg-indigo-600 {
        padding: 6px 10px !important;
        font-weight: 700;
        transform: none !important;
    }

    /* if some buttons still use large padding classes, force auto width */
    #dewasaTable .w-full { width: auto !important; }

    /* Modal tweaks */
    .modal-box.w-11\/12.max-w-5xl { max-width: 900px; padding: 1rem; }
    #formContainer { max-height: 75vh; overflow-y: auto; padding-bottom: 0.5rem; }
    .modal .btn-sm.btn-circle { width: 30px; height: 30px; font-size: 0.9rem; }

    /* Optional ultra-compact for very wide screens */
    @media (min-width: 1280px) {
        #dewasaTable { font-size: 0.72rem; }
        #dewasaTable thead th { font-size: 0.72rem; padding: 5px 6px !important; }
        #dewasaTable tbody td { padding: 5px 6px !important; }
        #dewasaTable .btn { font-size: 0.68rem !important; padding: 4px 8px !important; }
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.dataTables.min.css">

<script>
    let table;

    // FUNGSI MODAL DAISYUI
    function bukaModal() {
        const modal = document.getElementById('dewasaModal');
        modal.classList.add('modal-open');
        document.body.style.overflow = 'hidden';
    }

    function tutupModal() {
        const modal = document.getElementById('dewasaModal');
        modal.classList.remove('modal-open');
        document.body.style.overflow = 'auto';
        $('#formContainer').empty();
    }

    // Pastikan modal tertutup saat load halaman
    document.addEventListener('DOMContentLoaded', () => {
        tutupModal();
    });


    function closeDetailView() {
        const modal = document.getElementById('detailViewModal');
        const content = document.getElementById('detailViewContent');
        content.innerHTML = '<div id="detailLoading" class="text-center py-8"><span class="loading loading-spinner loading-lg"></span><div class="mt-3 text-sm text-gray-500">Memuat detail...</div></div>';
        if (modal.close) modal.close(); else modal.classList.remove('modal-open');
    }

    $(document).ready(function() {
        // INIT DATATABLE
        table = $('#dewasaTable').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: '{{ route("dewasa.data") }}',
                dataSrc: function(json) {
                    $('#totalWarga').text(json.data.length);
                    const belum = json.data.filter(d => (d.terakhir || '').toString().toLowerCase().includes('belum')).length;
                    $('#belumPeriksa').text(belum);
                    return json.data;
                }
            },
            columns: [
                { data: 'nik', className: 'text-sm' },
                { data: 'nama', render: d => `<strong class="text-teal-700">${d}</strong>` },
                { data: 'umur', className: 'text-center font-bold text-teal-600' },
                { data: 'terakhir', className: 'text-center' },
                { data: 'imt', className: 'text-center' },
                { data: 'td', className: 'text-center' },
                { data: 'puma', className: 'text-center font-bold' },
                { data: 'tbc', className: 'text-center' },
                { data: 'rujuk', className: 'text-center font-bold text-sm' },
                { data: 'periksa_id', visible: false },
                {
                    data: null,
                    orderable: false,
                    className: 'text-center whitespace-nowrap',
                    render: function(d) {
                        // make buttons compact
                        return `
                            <div class="flex gap-2 justify-center flex-wrap">
                                <button type="button" 
                                        onclick="bukaFormTambah(${d.id})"
                                        class="bg-teal-600 hover:bg-teal-700 text-white font-semibold py-2 px-3 rounded-md transition">
                                    Periksa
                                </button>

                                ${d.periksa_id ? `
                                <button type="button" 
                                        onclick="bukaDetail(${d.id})"
                                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-3 rounded-md transition">
                                    Detail
                                </button>` : ''}

                                <a href="dewasa/${d.id}/export-excel"
                                   class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-1 px-3 rounded-md transition text-sm"
                                   title="Export riwayat pemeriksaan warga ini ke Excel">
                                    Export
                                </a>
                            </div>`;
                    }
                }
            ],
            responsive: true,
            language: {
                search: "Cari:",
                searchPlaceholder: "NIK / Nama",
                lengthMenu: "Tampil _MENU_ data",
                info: "_START_ - _END_ dari _TOTAL_",
                paginate: { previous: "←", next: "→" }
            }
        });
    });

    // 1. Form Tambah Baru
    window.bukaFormTambah = function(wargaId) {
        tutupModal();
        $.get(`/dewasa/${wargaId}/form`)
            .done(function(html) {
                $('#formContainer').html(html);
                runScripts();
                $('#modalTitle').text('Pemeriksaan Baru');
                bukaModal();
            });
    }

    // 2. Buka Riwayat / Detail
    window.bukaDetail = function(wargaId) {
        tutupModal();
        $.get(`/dewasa/${wargaId}/riwayat`)
            .done(function(html) {
                $('#formContainer').html(html);
                $('#modalTitle').text('Riwayat Pemeriksaan');
                bukaModal();
            });
    }

    // 3. Edit dari Detail
    window.editDariDetail = function(wargaId, periksaId) {
        tutupModal();
        setTimeout(() => {
            $.get(`/dewasa/${wargaId}/edit/${periksaId}`)
                .done(function(html) {
                    $('#formContainer').html(html);
                    runScripts();
                    $('#modalTitle').text('Edit Pemeriksaan');
                    bukaModal();
                });
        }, 300);
    }

    // 4. Hapus dari Detail
    window.hapusPeriksa = function(periksaId, wargaId) {
        Swal.fire({
            title: 'Yakin hapus?',
            text: "Data akan hilang permanen!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, Hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `/dewasa/${periksaId}`,
                    method: 'DELETE',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function() {
                        table.ajax.reload(null, false);
                        bukaDetail(wargaId);
                        Swal.fire('Terhapus!', '', 'success');
                    }
                });
            }
        });
    }

    // Helper: jalankan semua <script> di dalam form
    function runScripts() {
        $('#formContainer script').each(function() {
            eval(this.textContent || this.innerHTML || this.text);
        });
    }

    // 5. Submit Form (Tambah/Edit)
    $(document).on('submit', '#ajaxForm', function(e) {
        e.preventDefault();
        let fd = new FormData(this);

        $.ajax({
            url: $(this).attr('action'),
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: function(res) {
                tutupModal();
                table.ajax.reload(null, false);
                Swal.fire('Sukses!', 'Data tersimpan', 'success');
            },
            error: function(xhr) {
                if (xhr.status === 422) {
                    $('.text-red-500').remove();
                    $('.input-error').removeClass('input-error');
                    $.each(xhr.responseJSON.errors, (field, msgs) => {
                        $(`[name="${field}"]`)
                            .addClass('input-error border-red-500')
                            .after(`<div class="text-red-500 text-xs mt-1">${msgs[0]}</div>`);
                    });
                }
            }
        });
    });

</script>
@endpush

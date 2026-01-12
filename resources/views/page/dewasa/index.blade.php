@extends('layouts.app')
@section('title', 'Pemeriksaan Dewasa & Lansia')
@section('content')
<div class="bg-white rounded-2xl shadow-xl overflow-hidden">
    <div class="bg-gradient-to-r from-teal-700 to-teal-900 text-white p-6 md:p-8">
        <!-- header tetap sama -->
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
        <!-- Tombol Export Selected + Semua -->
        <div class="flex flex-col sm:flex-row justify-between items-center gap-4 mb-4">
            <div class="flex flex-wrap gap-3">
                <button id="exportSelected" 
                        class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white font-medium rounded-lg disabled:opacity-50 disabled:cursor-not-allowed transition" 
                        disabled>
                    Export Selected ke Excel
                </button>
                <a href="{{ route('dewasa.exportSemua') }}" 
                   class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-medium rounded-lg">
                    Export Semua
                </a>
                <button id="filterHariIni" 
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition">
                    Sudah Periksa Hari Ini
                </button>
                <button id="filterSemua" 
                        class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-lg transition">
                    Tampilkan Semua
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table id="dewasaTable" class="table table-zebra w-full">
                <thead class="bg-teal-100 text-teal-900">
                    <tr>
                        <th class="w-10 text-center">
                            <input type="checkbox" id="selectAll" class="checkbox checkbox-success" />
                        </th>
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

<!-- Modal tetap sama -->
<div id="dewasaModal" class="modal">
    <div class="modal-box w-11/12 max-w-5xl bg-white">
        <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2" onclick="tutupModal()">X</button>
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

    /* ===========================
       MOBILE FORM POLISH (<=768px)
       =========================== */
    @media (max-width: 768px) {

        /* Header form */
        .modal-box h2 {
            font-size: 1.25rem !important; /* text-xl */
            line-height: 1.4;
        }

        /* Section title */
        .modal-box h3 {
            font-size: 1.05rem !important; /* ~text-base */
            margin-bottom: 0.75rem !important;
        }

        /* Reduce paddings */
        .modal-box .p-6 { padding: 1rem !important; }
        .modal-box .p-8 { padding: 1.25rem !important; }

        /* Reduce margins */
        .modal-box .mb-8 { margin-bottom: 1.25rem !important; }
        .modal-box .mt-8 { margin-top: 1.25rem !important; }
        .modal-box .mt-10 { margin-top: 1.5rem !important; }

        /* Inputs & selects */
        .modal-box input,
        .modal-box select,
        .modal-box textarea {
            font-size: 0.875rem !important; /* text-sm */
        }

        /* Result boxes (IMT, TD, Gula, PUMA, TBC) */
        #imt_display,
        #kategori_imt_display,
        #td_hasil,
        #gula_hasil {
            font-size: 1rem !important;
        }

        /* Big result numbers */
        #usia_display,
        #skor_usia_display,
        #total_puma,
        #hasil_puma,
        #hasil_tbc {
            font-size: 1.25rem !important;
        }

        /* Checkbox & radio */
        .checkbox-md,
        .checkbox-lg,
        .radio-md {
            transform: scale(0.9);
        }

        /* AKS / SKILAS cards */
        .rounded-3xl {
            border-radius: 1rem !important;
        }

        /* Buttons */
        .modal-action .btn {
            font-size: 0.875rem !important;
            padding: 0.5rem 1rem !important;
        }

        /* Submit button text */
        #btnSubmit {
            font-size: 1rem !important;
            padding: 0.75rem 1.5rem !important;
        }

        /* Reduce grid force */
        .grid.md\\:grid-cols-3,
        .grid.lg\\:grid-cols-4 {
            grid-template-columns: 1fr !important;
        }
    }
    
    /* ===========================
       GLOBAL FORM ROUNDED STYLE
       =========================== */

    /* Input, Select, Textarea */
    input[type="text"],
    input[type="number"],
    input[type="email"],
    input[type="password"],
    input[type="date"],
    input[type="time"],
    input[type="search"],
    input[type="tel"],
    select,
    textarea {
        border-radius: 0.75rem !important; /* rounded-xl */
    }

    /* Focus state tetap halus */
    input:focus,
    select:focus,
    textarea:focus {
        outline: none;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25); /* soft blue ring */
    }

    /* DaisyUI input override */
    .input,
    .select,
    .textarea {
        border-radius: 0.75rem !important;
    }

    /* Checkbox & radio tetap proporsional */
    .checkbox,
    .radio {
        border-radius: 0.5rem;
    }

    /* Tombol juga biar konsisten */
    .btn {
        border-radius: 0.75rem;
    }

    /* Badge biar ikut lembut */
    .badge {
        border-radius: 9999px;
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.dataTables.min.css">

<script>
    let table;
    let selectedIds = new Set();

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

    // Filter "Sudah Periksa Hari Ini"
    $('#filterHariIni').on('click', function() {
        // Reload table dengan parameter filter hari ini
        table.ajax.url('{{ route("dewasa.data") }}?filter=hari_ini').load();
        $(this).addClass('bg-indigo-800').siblings().removeClass('bg-indigo-800');
    });

    $('#filterSemua').on('click', function() {
        // Kembali ke semua data
        table.ajax.url('{{ route("dewasa.data") }}').load();
        $(this).addClass('bg-gray-800').siblings().removeClass('bg-gray-800');
    });

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
        table = $('#dewasaTable').DataTable({
            processing: true,
            serverSide: false,
            order: [],
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
                {
                    data: 'id',
                    orderable: false,
                    className: 'text-center',
                    render: function(data) {
                        return `<input type="checkbox" class="checkbox checkbox-success select-row" value="${data}" />`;
                    }
                },
                { data: 'nik', className: 'text-sm' },
                { data: 'nama', render: d => `<strong class="text-teal-700">${d}</strong>` },
                { data: 'umur', className: 'text-center font-bold text-teal-600' },
                { data: 'terakhir', className: 'text-center' },
                { data: 'imt', className: 'text-center' },
                { data: 'td', className: 'text-center' },
                { data: 'puma', className: 'text-center font-bold' },
                { data: 'tbc', className: 'text-center' },
                { data: 'rujuk', className: 'text-center font-bold text-sm' },
                {
                    data: null,
                    orderable: false,
                    className: 'text-center whitespace-nowrap',
                    render: function(d) {
                        return `
                            <div class="flex gap-2 justify-center flex-wrap">
                                <!-- Periksa -->
                                <button type="button"
                                    onclick="bukaFormTambah(${d.id})"
                                    class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-3 rounded-md transition">
                                    Periksa
                                </button>

                                <!-- Detail -->
                                ${d.periksa_id ? `
                                <button type="button"
                                    onclick="bukaDetail(${d.id})"
                                    class="bg-purple-600 hover:bg-purple-700 text-white font-semibold py-2 px-3 rounded-md transition">
                                    Detail
                                </button>` : ''}

                                <!-- Export -->
                                <a href="/dewasa/${d.id}/export-excel"
                                    class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-3 rounded-md transition text-sm flex items-center"
                                    title="Export riwayat pemeriksaan warga ini ke Excel">
                                    Export
                                </a>
                            </div>
                            `;
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

    // ================================================
    // FITUR EXPORT SELECTED - DEWASA
    // ================================================

    // Select All checkbox
    $('#selectAll').on('change', function() {
        $('.select-row').prop('checked', this.checked);
        updateExportButton();
    });

    // Saat checkbox row diubah
    $('#dewasaTable tbody').on('change', '.select-row', function() {
        const total = $('.select-row').length;
        const checked = $('.select-row:checked').length;
        
        $('#selectAll').prop('checked', total === checked && total > 0);
        
        // Highlight row yang dipilih
        const $row = $(this).closest('tr');
        if (this.checked) {
            $row.addClass('bg-teal-50');
        } else {
            $row.removeClass('bg-teal-50');
        }
        
        updateExportButton();
    });

    // Fungsi update tombol (dengan counter jumlah)
    function updateExportButton() {
        const count = $('.select-row:checked').length;
        $('#exportSelected')
            .prop('disabled', count === 0)
            .text(count > 0 ? `Export Selected (${count}) ke Excel` : 'Export Selected ke Excel');
    }

    // Handler klik tombol Export Selected
    $('#exportSelected').on('click', function() {
        const selectedIds = [];
        $('.select-row:checked').each(function() {
            selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0) {
            Swal.fire('Info', 'Pilih minimal satu data untuk di-export', 'info');
            return;
        }

        // Optional: batasi maksimal (misal 50) biar server tidak overload
        // if (selectedIds.length > 50) {
        //     Swal.fire('Batas Maksimal', 'Maksimal 50 data sekaligus', 'warning');
        //     return;
        // }

        const idsParam = selectedIds.join(',');
        window.location.href = `/dewasa/export-selected?ids=${idsParam}`;
    });

    // Inisialisasi awal
    updateExportButton();

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

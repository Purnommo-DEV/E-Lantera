@extends('layouts.app')
@section('title', 'Pemeriksaan Lansia')

@section('content')
<div class="bg-white rounded-2xl shadow-xl overflow-hidden">
    {{-- HEADER MIRIP INDEX2 --}}
    <div class="bg-gradient-to-r from-emerald-700 to-emerald-900 text-white p-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-6">
            <div>
                <h3 class="text-3xl font-bold">Pemeriksaan Lansia (AKS & SKILAS)</h3>
                <p class="opacity-90 mt-2">RW xx - Dusun Cipulir Estate, Cipadu Jaya, Larangan</p>
            </div>
            <div class="text-right space-y-1">
                <p class="text-sm opacity-90">
                    Total Lansia ≥60 thn:
                    <span id="totalLansia" class="font-bold text-2xl">0</span> orang
                </p>
                <p class="text-sm opacity-90">
                    Belum pernah diperiksa:
                    <span id="belumPeriksa" class="font-bold text-yellow-300 text-2xl">0</span> orang
                </p>
            </div>
        </div>
    </div>

    {{-- TABEL DATATABLES --}}
    <div class="p-8">
        <div class="overflow-x-auto">
            <table id="lansiaTable" class="table table-zebra w-full">
                <thead class="bg-emerald-100 text-emerald-900">
                <tr>
                    <th>#</th>
                    <th>NIK</th>
                    <th>Nama</th>
                    <th>Umur</th>
                    <th>Terakhir Periksa</th>
                    <th>AKS Total</th>
                    <th>AKS Kategori</th>
                    <th>SKILAS (+)</th>
                    <th>Status</th>
                    <th class="text-center">Aksi</th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

{{-- ============== MODAL DAISYUI MIRIP INDEX2 ============== --}}
<div id="lansiaModal" class="modal">
    <div class="modal-box w-11/12 max-w-6xl bg-white">
        <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2" onclick="tutupModal()">
            ✕
        </button>
        <h3 class="text-3xl font-bold mb-8 text-emerald-700">
            Pemeriksaan Lansia
        </h3>
        <div id="lansiaModalBody" class="overflow-y-auto max-h-screen">
            {{-- form akan dimuat via AJAX di sini --}}
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    /* Wrapper DataTables utk lansiaTable saja */
    #lansiaTable_wrapper .dataTables_length,
    #lansiaTable_wrapper .dataTables_filter {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
    }

    #lansiaTable_wrapper .dataTables_length {
        float: left;
    }

    #lansiaTable_wrapper .dataTables_filter {
        float: right;
    }

    #lansiaTable_wrapper .dataTables_length label,
    #lansiaTable_wrapper .dataTables_filter label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin: 0;
        font-size: 0.875rem; /* biar agak compact */
    }

    #lansiaTable_wrapper .dataTables_length select {
        border-radius: 0.5rem;
        padding: 0.15rem 0.4rem;
    }

    #lansiaTable_wrapper .dataTables_filter input {
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        padding: 0.25rem 0.5rem;
    }

    /* Responsive: di layar kecil, jadikan 2 baris */
    @media (max-width: 768px) {
        #lansiaTable_wrapper .dataTables_length,
        #lansiaTable_wrapper .dataTables_filter {
            float: none;
            width: 100%;
            justify-content: space-between;
        }

        #lansiaTable_wrapper .dataTables_filter {
            margin-top: 0.25rem;
        }
    }

    /* warna background ketika checked */
    .checkbox-primary:checked,
    .checkbox-primary:checked:hover {
        background-color: #4f46e5 !important; /* Indigo-600 */
        border-color: #4f46e5 !important;
    }

    /* warna centang */
    .checkbox-primary:checked::before {
        color: white !important;
        transform: scale(1.4); /* perbesar centang */
    }

    /* ukuran checkbox keseluruhan */
    .checkbox.checkbox-lg {
        width: 1.6rem;
        height: 1.6rem;
    }

    /* Fix textarea putih-on-putih */
    textarea {
        color: #1f2937 !important; /* text-gray-800 */
    }
    textarea::placeholder {
        color: #9ca3af !important; /* text-gray-400 */
    }
    /* Kalau tetap putih di mode dark (jika pakai) */
    .dark textarea {
        color: #e5e7eb !important;
    }

</style>
@endpush


@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.dataTables.min.css">

<script>
    // ==================================================
    // SEMUA FUNGSI GLOBAL — HARUS DI ATAS DOMContentLoaded
    // ==================================================

    // Modal DaisyUI
    window.bukaModal = function () {
        const modal = document.getElementById('lansiaModal');
        modal.classList.add('modal-open');
        document.body.style.overflow = 'hidden';
    };

    window.tutupModal = function () {
        const modal = document.getElementById('lansiaModal');
        const modalBody = document.getElementById('lansiaModalBody');
        modal.classList.remove('modal-open');
        document.body.style.overflow = 'auto';
        modalBody.innerHTML = ''; // bersihkan isi modal
    };

    // SKILAS — Ya/Tidak & Tes Bisik tidak boleh dicentang bareng
    window.uncheckSibling = function(el, siblingName) {
        const sibling = document.querySelector(`input[name="${siblingName}"]`);
        if (sibling && sibling !== el) sibling.checked = false;
    };

    window.uncheckGroup = function(el, names) {
        names.forEach(name => {
            const input = document.querySelector(`input[name="${name}"]`);
            if (input && input !== el) input.checked = false;
        });
    };

    // AKS Auto Hitung — dipanggil setiap form dimuat
    window.initAksCalculator = function () {
        const aksContainer = document.getElementById('aks-container');
        if (!aksContainer) return;

        const totalSkorEl   = document.getElementById('total-skor');
        const kategoriEl    = document.getElementById('kategori-aks');
        const resultBox     = document.getElementById('aks-result-box');
        const rujukWarn     = document.getElementById('rujuk-warning');

        function hitungAks() {
            let total = 0;
            document.querySelectorAll('.aks-checkbox:checked').forEach(cb => {
                total += parseInt(cb.dataset.skor || '0', 10);
            });

            totalSkorEl.textContent = total;

            let kategori = 'Total Ketergantungan (T)';
            let warnaBox = 'bg-red-700';
            let perluRujuk = true;

            if (total >= 20) {
                kategori = 'Mandiri (M)';
                warnaBox = 'bg-green-600';
                perluRujuk = false;
            } else if (total >= 12) {
                kategori = 'Risiko Ringan (R)';
                warnaBox = 'bg-lime-600';
                perluRujuk = false;
            } else if (total >= 9) {
                kategori = 'Sedang (S)';
                warnaBox = 'bg-yellow-600';
            } else if (total >= 5) {
                kategori = 'Berat (B)';
                warnaBox = 'bg-orange-600';
            }

            kategoriEl.textContent = kategori;
            resultBox.classList.remove('bg-green-600','bg-lime-600','bg-yellow-600','bg-orange-600','bg-red-700');
            resultBox.classList.add(warnaBox);
            rujukWarn.classList.toggle('hidden', !perluRujuk);
        }

        hitungAks(); // jalankan saat form pertama kali masuk modal
        aksContainer.addEventListener('change', e => {
            if (e.target.classList.contains('aks-checkbox')) hitungAks();
        });
    };

    // ==================================================
    // SEMUA LOGIC UTAMA — JALAN SETELAH DOM READY
    // ==================================================
    document.addEventListener('DOMContentLoaded', function () {

        // URL Template
        const formUrlTemplate     = @json(route('lansia.form', ['warga' => '__ID__']));
        const riwayatUrlTemplate    = @json(route('lansia.riwayat', ['warga' => '__ID__']));
        const exportUrlTemplate     = @json(route('lansia.export', ['warga' => '__ID__']));

        // ==================== DATATABLES ====================
        const table = $('#lansiaTable').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: @json(route('lansia.data')),
                type: 'GET',
                dataSrc: function (json) {
                    const data = json.data || [];
                    $('#totalLansia').text(data.length);
                    const belum = data.filter(row => !row.terakhir || String(row.terakhir).toLowerCase().includes('belum')).length;
                    $('#belumPeriksa').text(belum);
                    return data;
                }
            },
            columns: [
                { data: null, render: (data, type, row, meta) => meta.row + 1, className: 'text-center' },
                { data: 'nik', className: 'text-sm' },
                { data: 'nama', render: d => `<strong class="text-emerald-700">${d}</strong>` },
                { data: 'umur', className: 'text-center font-bold text-emerald-600' },
                { data: 'terakhir', className: 'text-center' },
                { data: 'aks_total_skor', className: 'text-center font-bold' },
                { data: 'aks_kategori', className: 'text-center' },
                { data: 'skilas_positif', className: 'text-center' },
                { data: 'perlu_rujuk', className: 'text-center',
                  render: d => d
                    ? '<span class="text-red-600 font-bold text-lg">RUJUK</span>'
                    : '<span class="text-green-600 font-semibold">Aman</span>' },
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    className: 'text-center whitespace-nowrap',
                    render: function (id, type, row) {
                        const urlForm    = formUrlTemplate.replace('__ID__', id);
                        const urlRiwayat = riwayatUrlTemplate.replace('__ID__', id);
                        const urlExport  = exportUrlTemplate.replace('__ID__', id);

                        const btnRiwayat = row.periksa_id
                            ? `<button type="button" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-xl shadow btn-riwayat" data-url="${urlRiwayat}">Detail</button>`
                            : '';

                        return `
                            <div class="flex gap-2 justify-center flex-wrap">
                                <button type="button" 
                                        class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 px-4 rounded-xl shadow btn-periksa" 
                                        data-url="${urlForm}">
                                    Periksa
                                </button>
                                ${btnRiwayat}
                                <a href="${urlExport}" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 px-4 rounded-xl shadow-lg transition text-sm">
                                    Export Excel
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

        // ==================== KLIK TOMBOL (Periksa / Riwayat / Edit) ====================
        $(document).on('click', '.btn-periksa, .btn-riwayat, .btn-edit-periksa', function(e) {
            console.log('CLICK BUTTON:', this.className);
            e.preventDefault();           // INI YANG PENTING! cegah reload
            e.stopPropagation();

            const btn = $(this);
            const url = btn.data('url');

            if (!url) {
                console.warn('Tombol belum punya data-url (tabel masih loading)');
                return;
            }

            const modalBody = document.getElementById('lansiaModalBody');
            modalBody.innerHTML = '<div class="text-center py-20"><span class="loading loading-spinner loading-lg"></span></div>';
            bukaModal();

            fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => {
                if (!res.ok) throw new Error('Network error');
                return res.text();
            })
            .then(html => {
                modalBody.innerHTML = html;
                window.initAksCalculator?.();

                // Ubah judul kalau edit
                if (btn.hasClass('btn-edit-periksa')) {
                    document.querySelector('#lansiaModal h3').innerHTML = '<span class="text-orange-500 font-bold">Edit</span> Pemeriksaan Lansia';
                }
            })
            .catch(err => {
                console.error(err);
                modalBody.innerHTML = '<div class="text-red-600 text-center py-20 text-xl font-bold">Gagal memuat formulir!</div>';
            });
        });

        // ==================== SUBMIT FORM VIA AJAX ====================
        $(document).on('submit', '#ajaxForm', function (e) {
            e.preventDefault();

            const form = this;
            const submitBtn = form.querySelector('button[type="submit"]');
            const fd = new FormData(form);
            const url = form.action;

            submitBtn.disabled = true;
            submitBtn.classList.add('loading');

            $.ajax({
                url: url,
                method: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                success: function () {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('loading');
                    tutupModal();
                    table.ajax.reload(null, false);
                    Swal.fire({
                        icon: 'success',
                        title: 'Sukses!',
                        text: 'Pemeriksaan berhasil disimpan',
                        timer: 1800,
                        showConfirmButton: false
                    });
                },
                error: function (xhr) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('loading');

                    // Hapus error lama
                    $('.text-red-500').remove();
                    $('.input-error').removeClass('input-error');

                    if (xhr.status === 422) {
                        $.each(xhr.responseJSON.errors, function (field, msgs) {
                            const input = $(`[name="${field}"]`);
                            input.addClass('input-error border-red-500');
                            input.after(`<div class="text-red-500 text-xs mt-1">${msgs[0]}</div>`);
                        });
                        Swal.fire({
                            icon: 'error',
                            title: 'Validasi Gagal',
                            text: 'Silakan periksa kembali isian Anda',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Terjadi kesalahan saat menyimpan data'
                        });
                    }
                }
            });
        });

        // ==================== SAFETY: Cegah Ya + Tidak dicentang bareng di SKILAS ====================
        function fixDoubleChecked() {
            document.querySelectorAll('.flex.gap-6').forEach(group => {
                const checked = group.querySelectorAll('input[type="checkbox"]:checked');
                if (checked.length > 1) {
                    Array.from(checked).slice(1).forEach(cb => cb.checked = false);
                }
            });
        }
        fixDoubleChecked();

        // Tetap jalan meski form diganti via AJAX
        new MutationObserver(fixDoubleChecked).observe(document.body, { childList: true, subtree: true });
    });
</script>
@endpush
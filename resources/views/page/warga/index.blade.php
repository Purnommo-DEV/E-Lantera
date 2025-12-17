@extends('layouts.app')
@section('title', 'Data Warga Posyandu')

@section('content')
<div class="bg-white rounded-2xl shadow-xl overflow-hidden">
    <div class="bg-gradient-to-r from-red-700 to-red-900 text-white p-4 md:p-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 md:gap-6">
            <div>
                <h3 class="text-2xl md:text-3xl font-bold">Data Warga Posyandu</h3>
                <p class="opacity-90 mt-1 text-sm md:text-base">RW xx - Dusun Cipulir Estate, Cipadu Jaya, Larangan</p>
            </div>
            <button onclick="addWarga()"
                    class="bg-yellow-400 hover:bg-yellow-500 text-red-900 font-bold py-2 px-4 md:py-3 md:px-6 rounded-md text-sm md:text-base shadow transition transform hover:scale-102">
                + Tambah Warga
            </button>
        </div>
    </div>

    <div class="p-4 md:p-6">
        <div class="overflow-x-auto">
            <table id="wargaTable" class="table table-zebra w-full text-sm">
                <thead class="bg-red-100 text-red-900">
                    <tr>
                        <th>NIK</th>
                        <th>Nama Lengkap</th>
                        <th>Umur</th>
                        <th>JK</th>
                        <th>Alamat</th>
                        <th>No. HP</th>
                        <th>Status Nikah</th>
                        <th>Pekerjaan</th>
                        <th>Catatan</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal DaisyUI - Modern -->
<dialog id="wargaModal" class="modal modal-bottom sm:modal-middle">
    <div class="modal-box w-11/12 max-w-4xl p-0 max-h-[90vh] flex flex-col">

        <!-- HEADER -->
        <div class="flex items-center justify-between px-6 py-4 bg-gradient-to-r from-red-600 to-red-500 text-white shrink-0">
            <h3 class="text-lg md:text-xl font-bold" id="modalTitle">Tambah Warga Baru</h3>
            <button class="btn btn-sm btn-circle btn-ghost text-white"
                onclick="wargaModal.close()">✕</button>
        </div>

        <!-- BODY -->
        <form id="wargaForm" class="flex-1 overflow-y-auto px-6 py-5 space-y-5">
            @csrf
            <input type="hidden" id="wargaId">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <!-- NIK -->
                <div class="form-control">
                    <label class="label font-medium">NIK</label>
                    <input type="text" name="nik" maxlength="16" data-autotab required
                        class="input input-bordered w-full rounded-xl">
                    <p class="error-message text-red-500 text-sm mt-1 hidden"></p>
                </div>

                <!-- NAMA -->
                <div class="form-control">
                    <label class="label font-medium">Nama Lengkap</label>
                    <input type="text" name="nama" required
                        class="input input-bordered w-full rounded-xl">
                    <p class="error-message text-red-500 text-sm mt-1 hidden"></p>
                </div>

                <!-- TGL LAHIR -->
                <div class="form-control">
                    <label class="label font-medium">Tanggal Lahir</label>
                    <input type="date" name="tanggal_lahir" required
                        class="input input-bordered w-full rounded-xl">
                    <p class="error-message text-red-500 text-sm mt-1 hidden"></p>
                </div>

                <!-- JK -->
                <div class="form-control">
                    <label class="label font-medium">Jenis Kelamin</label>
                    <select name="jenis_kelamin" required
                        class="select select-bordered w-full rounded-xl">
                        <option value="">Pilih</option>
                        <option>Laki-laki</option>
                        <option>Perempuan</option>
                    </select>
                    <p class="error-message text-red-500 text-sm mt-1 hidden"></p>
                </div>

                <!-- DUSUN -->
                <div class="form-control">
                    <label class="label font-medium">Dusun</label>
                    <input type="text" name="dusun" required
                        class="input input-bordered w-full rounded-xl">
                    <p class="error-message text-red-500 text-sm mt-1 hidden"></p>
                </div>

                <!-- RT RW -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="form-control">
                        <label class="label font-medium">RT</label>
                        <input type="text" name="rt" maxlength="3" data-autotab required
                            class="input input-bordered w-full rounded-xl">
                        <p class="error-message text-red-500 text-sm mt-1 hidden"></p>
                    </div>
                    <div class="form-control">
                        <label class="label font-medium">RW</label>
                        <input type="text" name="rw" maxlength="3" data-autotab required
                            class="input input-bordered w-full rounded-xl">
                        <p class="error-message text-red-500 text-sm mt-1 hidden"></p>
                    </div>
                </div>

                <!-- ALAMAT -->
                <div class="form-control md:col-span-2">
                    <label class="label font-medium">Alamat</label>
                    <textarea name="alamat" rows="2" required
                        class="textarea textarea-bordered w-full rounded-xl"></textarea>
                    <p class="error-message text-red-500 text-sm mt-1 hidden"></p>
                </div>

                <!-- HP -->
                <div class="form-control">
                    <label class="label font-medium">No HP</label>
                    <input type="text" name="no_hp"
                        class="input input-bordered w-full rounded-xl">
                    <p class="error-message text-red-500 text-sm mt-1 hidden"></p>
                </div>

                <!-- STATUS -->
                <div class="form-control">
                    <label class="label font-medium">Status Nikah</label>
                    <select name="status_nikah" required
                        class="select select-bordered w-full rounded-xl">
                        <option>Menikah</option>
                        <option>Tidak Menikah</option>
                    </select>
                    <p class="error-message text-red-500 text-sm mt-1 hidden"></p>
                </div>

                <!-- PEKERJAAN -->
                <div class="form-control md:col-span-2">
                    <label class="label font-medium">Pekerjaan</label>
                    <input type="text" name="pekerjaan"
                        class="input input-bordered w-full rounded-xl">
                    <p class="error-message text-red-500 text-sm mt-1 hidden"></p>
                </div>

                <!-- CATATAN -->
                <div class="form-control md:col-span-2">
                    <label class="label font-medium">Catatan</label>
                    <textarea name="catatan" rows="3"
                        class="textarea textarea-bordered w-full rounded-xl"></textarea>
                    <p class="error-message text-red-500 text-sm mt-1 hidden"></p>
                </div>
            </div>
        </form>

        <!-- FOOTER -->
        <div class="sticky bottom-0 bg-base-100 border-t px-6 py-4 flex justify-end gap-2">
            <button class="btn btn-ghost" onclick="wargaModal.close()">Batal</button>
            <button id="btnSubmitWarga" type="submit" form="wargaForm"
                class="btn bg-red-600 hover:bg-red-700 text-white flex items-center gap-2">
                <span class="btn-text">Simpan Warga</span>
                <span class="btn-spinner hidden animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full"></span>
            </button>
        </div>
    </div>
</dialog>
@endsection

@push('styles')
<style>

    .input-error,.select-error,.textarea-error{border-color:#ef4444!important}
    .btn-loading{pointer-events:none;opacity:.8}
    /* Compact style tweaks for warga table & modal */

    /* DataTables wrapper compact */
    #wargaTable_wrapper .dataTables_length,
    #wargaTable_wrapper .dataTables_filter {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        margin-bottom: 0.5rem;
    }

    #wargaTable_wrapper .dataTables_length label,
    #wargaTable_wrapper .dataTables_filter label {
        margin: 0;
        font-size: 0.78rem;
    }

    #wargaTable_wrapper .dataTables_length select,
    #wargaTable_wrapper .dataTables_filter input {
        font-size: 0.78rem;
        padding: 0.18rem 0.4rem;
        border-radius: 0.35rem;
    }

    /* table compact */
    #wargaTable { font-size: 0.78rem; }
    #wargaTable thead th {
        padding: 6px 8px !important;
        font-size: 0.78rem !important;
    }
    #wargaTable tbody td {
        padding: 6px 8px !important;
        line-height: 1.12 !important;
        vertical-align: middle;
    }

    /* compact pagination/info */
    #wargaTable_wrapper .dataTables_paginate,
    #wargaTable_wrapper .dataTables_info,
    #wargaTable_wrapper .dataTables_length {
        font-size: 0.78rem !important;
    }

    /* action buttons small */
    #wargaTable .btn,
    #wargaTable button {
        font-size: 0.7rem !important;
        padding: 5px 8px !important;
        border-radius: 0.35rem !important;
    }

    /* spacing for header action button */
    .bg-yellow-400 { /* keep visual but slightly smaller on wide screens */
        transition: transform .12s;
    }

    /* Modal compact */
    .modal-box.w-11\/12.max-w-3xl { max-width: 760px; padding: 0.75rem; }
    .modal .btn-sm { padding: 6px 8px; font-size: 0.78rem; }

    /* form inputs small */
    .input-sm, .select-sm, .textarea-sm {
        padding: 0.4rem 0.5rem;
        font-size: 0.88rem;
    }

    /* ensure long text in catatan doesn't overflow */
    #wargaTable td { word-break: break-word; }

    /* responsive tweaks for very small screens */
    @media (max-width: 640px) {
        #wargaTable thead th { font-size: 0.72rem; padding: 5px 6px !important; }
        #wargaTable tbody td { font-size: 0.72rem; padding: 5px 6px !important; }
        .modal-box.w-11\/12.max-w-3xl { padding: 0.5rem; }
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.dataTables.min.css">

<script>
    const modal = document.getElementById('wargaModal');
    const form  = document.getElementById('wargaForm');
    const btnSubmit = $('#btnSubmitWarga');
    let table;

    /* =====================
       DATATABLE
    ===================== */
    $(document).ready(function () {
        table = $('#wargaTable').DataTable({
            processing: true,
            serverSide: false,
            order: [], // 🔥 URUTAN MURNI DARI BACKEND
            ajax: '{{ route('warga.data') }}',
            dom: '<"flex justify-between items-center mb-4"lf>rt<"flex justify-between items-center mt-4"ip>',
            columns: [
                { data: 'nik' },
                { data: 'nama', render: d => `<span class="font-bold">${d}</span>` },
                { data: 'umur', className: 'text-center font-bold text-red-700' },
                { data: 'jenis_kelamin', className: 'text-center' },
                { data: 'alamat' },
                { data: 'no_hp' },
                { data: 'status_nikah', className: 'text-center' },
                { data: 'pekerjaan' },
                { data: 'catatan', orderable: false },
                {
                    data: null,
                    className: 'text-center',
                    render: d => `
                        <div class="flex gap-2 justify-center">
                            <button onclick="editWarga(${d.id})" class="btn btn-warning btn-sm">Edit</button>
                            <button onclick="deleteWarga(${d.id})" class="btn btn-error btn-sm">Hapus</button>
                        </div>`
                }
            ]
        });
    });

    /* =====================
       HELPER
    ===================== */
    function startLoading() {
        btnSubmit.addClass('btn-loading').prop('disabled', true);
        btnSubmit.find('.btn-text').text('Menyimpan...');
        btnSubmit.find('.btn-spinner').removeClass('hidden');
    }

    function stopLoading() {
        btnSubmit.removeClass('btn-loading').prop('disabled', false);
        btnSubmit.find('.btn-text').text('Simpan Warga');
        btnSubmit.find('.btn-spinner').addClass('hidden');
    }

    function vibrateError() {
        if (navigator.vibrate) navigator.vibrate([100, 50, 100]);
    }

    function clearFieldError(input) {
        const wrapper = input.closest('.form-control');
        wrapper.find('.error-message').text('').addClass('hidden');
        input.removeClass('input-error select-error textarea-error');
    }

    /* =====================
       OPEN MODAL
    ===================== */
    window.addWarga = function () {
        form.reset();
        $('#wargaId').val('');
        $('#modalTitle').text('Tambah Warga Baru');

        $('.error-message').text('').addClass('hidden');
        $('.input, .select, .textarea')
            .removeClass('input-error select-error textarea-error');

        modal.showModal();
    }

    window.editWarga = function (id) {
        $.get('/warga/' + id, function (data) {

            // reset form dulu (WAJIB)
            form.reset();

            $('#wargaId').val(data.id);

            // isi field lain (tanpa tanggal_lahir)
            Object.keys(data).forEach(key => {
                if (key !== 'tanggal_lahir') {
                    const field = $(`[name="${key}"]`);
                    if (field.length) field.val(data[key]);
                }
            });

            $('#modalTitle').text('Edit: ' + data.nama);

            // reset error
            $('.error-message').text('').addClass('hidden');
            $('.input, .select, .textarea')
                .removeClass('input-error select-error textarea-error');

            // 🔥 BUKA MODAL DULU
            if (modal.showModal) modal.showModal();
            else modal.classList.add('modal-open');

            // 🔥 SET TANGGAL LAHIR SETELAH MODAL TERBUKA
            if (data.tanggal_lahir) {
                setTimeout(() => {
                    document.querySelector('input[name="tanggal_lahir"]').value =
                        data.tanggal_lahir;
                }, 0);
            }
        })
        .fail(() => {
            Swal.fire('Error', 'Gagal memuat data warga', 'error');
        });
    };



    /* =====================
       DELETE
    ===================== */
    window.deleteWarga = function (id) {
        Swal.fire({
            title: 'Hapus warga?',
            text: 'Data akan dihapus permanen',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, hapus'
        }).then(r => {
            if (r.isConfirmed) {
                $.ajax({
                    url: '/warga/' + id,
                    type: 'DELETE',
                    data: { _token: '{{ csrf_token() }}' },
                    success: () => {
                        table.ajax.reload();
                        Swal.fire('Terhapus', 'Data berhasil dihapus', 'success');
                    }
                });
            }
        });
    }

    /* =====================
       REALTIME CLEAR ERROR
    ===================== */
    $('#wargaForm').on('input change', 'input, textarea, select', function () {
        clearFieldError($(this));
    });

    /* =====================
       AUTO TAB
    ===================== */
    function focusNextField(current) {
        const fields = $('#wargaForm')
            .find('input, select, textarea')
            .filter(':visible:not([disabled]):not([readonly])');

        const index = fields.index(current);
        if (index > -1 && index + 1 < fields.length) {
            fields.eq(index + 1).focus();
        }
    }

    $('#wargaForm').on('input', 'input[data-autotab]', function (e) {
        if (e.inputType === 'deleteContentBackward') return;

        const max = $(this).attr('maxlength');
        if (max && this.value.length >= max) {
            setTimeout(() => focusNextField($(this)), 100);
        }
    });

    $('#wargaForm').on('input', 'input[name="rt"], input[name="rw"]', function () {
        this.value = this.value.replace(/\D/g, '');
    });

    /* =====================
       SUBMIT FORM
    ===================== */
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        startLoading();

        const id = $('#wargaId').val();
        const url = id ? `/warga/${id}` : '/warga';
        const formData = new FormData(this);
        if (id) formData.append('_method', 'PUT');

        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,

            success: function () {
                stopLoading();
                modal.close();
                table.ajax.reload();
                Swal.fire('Sukses', 'Data warga tersimpan', 'success');
            },

            error: function (xhr) {
                stopLoading();
                vibrateError();

                if (xhr.status === 422) {
                    let firstError = null;
                    const errors = xhr.responseJSON.errors;

                    Object.keys(errors).forEach(field => {
                        const input = $(`[name="${field}"]`);
                        const wrapper = input.closest('.form-control');

                        input.addClass(
                            input.is('select') ? 'select-error' :
                            input.is('textarea') ? 'textarea-error' :
                            'input-error'
                        );

                        wrapper.find('.error-message')
                            .text(errors[field][0])
                            .removeClass('hidden');

                        if (!firstError) firstError = input;
                    });

                    if (firstError) {
                        firstError[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstError.focus();
                    }
                } else {
                    Swal.fire('Error', 'Terjadi kesalahan sistem', 'error');
                }
            }
        });
    });
</script>

@endpush

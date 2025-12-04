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

<!-- Modal DaisyUI -->
<dialog id="wargaModal" class="modal">
    <div class="modal-box w-11/12 max-w-3xl p-4 md:p-6">
        <div class="flex items-start justify-between">
            <h3 class="text-xl md:text-2xl font-bold mb-2" id="modalTitle">Tambah Warga Baru</h3>
            <button class="btn btn-sm btn-circle btn-ghost" onclick="wargaModal.close()">✕</button>
        </div>

        <form id="wargaForm" class="space-y-4">
            @csrf
            <input type="hidden" id="wargaId">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 md:gap-4">
                <div>
                    <label class="label font-semibold text-sm">NIK</label>
                    <input type="text" name="nik" maxlength="16" class="input input-bordered w-full input-sm" required>
                </div>
                <div>
                    <label class="label font-semibold text-sm">Nama Lengkap</label>
                    <input type="text" name="nama" class="input input-bordered w-full input-sm" required>
                </div>
                <div>
                    <label class="label font-semibold text-sm">Tanggal Lahir</label>
                    <input type="date" name="tanggal_lahir" class="input input-bordered w-full input-sm" required>
                </div>
                <div>
                    <label class="label font-semibold text-sm">Jenis Kelamin</label>
                    <select name="jenis_kelamin" class="select select-bordered w-full select-sm" required>
                        <option value="">Pilih</option>
                        <option value="Laki-laki">Laki-laki</option>
                        <option value="Perempuan">Perempuan</option>
                    </select>
                </div>
                <div>
                    <label class="label font-semibold text-sm">Dusun</label>
                    <input type="text" name="dusun" class="input input-bordered w-full input-sm" required>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="label font-semibold text-sm">RT</label>
                        <input type="text" name="rt" maxlength="3" class="input input-bordered w-full input-sm" required>
                    </div>
                    <div>
                        <label class="label font-semibold text-sm">RW</label>
                        <input type="text" name="rw" maxlength="3" class="input input-bordered w-full input-sm" required>
                    </div>
                </div>
                <div class="md:col-span-2">
                    <label class="label font-semibold text-sm">Alamat Lengkap</label>
                    <textarea name="alamat" class="textarea textarea-bordered w-full textarea-sm" rows="2" required></textarea>
                </div>
                <div>
                    <label class="label font-semibold text-sm">No. HP</label>
                    <input type="text" name="no_hp" class="input input-bordered w-full input-sm">
                </div>
                <div>
                    <label class="label font-semibold text-sm">Status Nikah</label>
                    <select name="status_nikah" class="select select-bordered w-full select-sm" required>
                        <option value="Menikah">Menikah</option>
                        <option value="Tidak Menikah">Tidak Menikah</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="label font-semibold text-sm">Pekerjaan</label>
                    <input type="text" name="pekerjaan" class="input input-bordered w-full input-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="label font-semibold text-sm">Catatan (opsional)</label>
                    <textarea name="catatan" class="textarea textarea-bordered w-full textarea-sm" rows="3" placeholder="Misal: Warga pindahan..."></textarea>
                </div>
            </div>

            <div class="modal-action pt-2">
                <button type="button" class="btn btn-ghost btn-sm" onclick="wargaModal.close()">Batal</button>
                <button type="submit" class="btn bg-red-600 hover:bg-red-700 text-white btn-sm">
                    Simpan Warga
                </button>
            </div>
        </form>
    </div>
</dialog>
@endsection

@push('styles')
<style>
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
    const form = document.getElementById('wargaForm');
    let table;

    $(document).ready(function() {
        table = $('#wargaTable').DataTable({
            processing: true,
            serverSide: false,
            ajax: '{{ route('warga.data') }}',
            dom: '<"flex justify-between items-center mb-4"lf>rt<"flex justify-between items-center mt-4"ip>',
            language: { search: "Cari NIK / Nama:", processing: "Memuat data warga..." },
            columns: [
                { data: 'nik' },
                { data: 'nama', render: d => `<span class="font-bold">${d}</span>` },
                { data: 'umur', className: 'text-center font-bold text-red-700' },
                { data: 'jenis_kelamin', className: 'text-center' },
                { data: 'alamat' },
                { data: 'no_hp' },
                { data: 'status_nikah', className: 'text-center' },
                { data: 'pekerjaan' },
                { data: 'catatan', orderable: false, render: d => d },
                {
                    data: null,
                    className: 'text-center',
                    render: d => `
                        <div class="flex gap-2 justify-center flex-wrap">
                            <button onclick="editWarga(${d.id})" class="btn btn-warning btn-sm">Edit</button>
                            <button onclick="deleteWarga(${d.id})" class="btn btn-error btn-sm ml-2">Hapus</button>
                        </div>
                    `
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

    window.addWarga = function() {
        form.reset();
        $('#wargaId').val('');
        document.getElementById('modalTitle').textContent = 'Tambah Warga Baru';
        $('.text-red-500').remove(); // Hapus error lama
        $('.input-error, .select-error').removeClass('input-error select-error');
        if (modal.showModal) modal.showModal(); else modal.classList.add('modal-open');
    }

    window.editWarga = function(id) {
        $.get('/warga/' + id, function(data) {
            $('#wargaId').val(data.id);

            $('[name="nik"]').val(data.nik);
            $('[name="nama"]').val(data.nama);

            if (data.tanggal_lahir) {
                const tgl = new Date(data.tanggal_lahir);
                const formatted = tgl.getFullYear() + '-' +
                                 String(tgl.getMonth() + 1).padStart(2, '0') + '-' +
                                 String(tgl.getDate()).padStart(2, '0');
                $('[name="tanggal_lahir"]').val(formatted);
            } else {
                $('[name="tanggal_lahir"]').val('');
            }

            $('[name="jenis_kelamin"]').val(data.jenis_kelamin);
            $('[name="alamat"]').val(data.alamat);
            $('[name="dusun"]').val(data.dusun);
            $('[name="rt"]').val(data.rt);
            $('[name="rw"]').val(data.rw);
            $('[name="no_hp"]').val(data.no_hp);
            $('[name="status_nikah"]').val(data.status_nikah);
            $('[name="pekerjaan"]').val(data.pekerjaan);
            $('[name="catatan"]').val(data.catatan || '');

            document.getElementById('modalTitle').textContent = 'Edit: ' + data.nama;

            $('.text-red-500').remove();
            $('.input-error, .select-error').removeClass('input-error select-error');

            if (modal.showModal) modal.showModal(); else modal.classList.add('modal-open');
        }).fail(function() {
            Swal.fire('Error', 'Gagal memuat data warga', 'error');
        });
    }

    window.deleteWarga = function(id) {
        Swal.fire({
            title: 'Hapus warga ini?',
            text: "Data akan hilang permanen!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, hapus!'
        }).then(r => {
            if (r.isConfirmed) {
                $.ajax({
                    url: '/warga/' + id,
                    type: 'DELETE',
                    data: { _token: '{{ csrf_token() }}' },
                    success: () => {
                        table.ajax.reload();
                        Swal.fire('Terhapus!', 'Data warga telah dihapus.', 'success');
                    }
                });
            }
        });
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        $('.text-red-500').remove();
        $('.input-error, .select-error').removeClass('input-error select-error');

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
            success: function() {
                if (modal.close) modal.close(); else modal.classList.remove('modal-open');
                table.ajax.reload();
                Swal.fire('Sukses!', 'Data warga tersimpan!', 'success');
            },
            error: function(xhr) {
                if (xhr.status === 422) { // Error validasi Laravel
                    const errors = xhr.responseJSON.errors;
                    Object.keys(errors).forEach(field => {
                        const input = $(`[name="${field}"]`);
                        input.addClass('input-error');
                        input.closest('div').append(
                            `<div class="text-red-500 text-sm mt-1 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                ${errors[field][0]}
                            </div>`
                        );
                    });
                    Swal.fire('Gagal!', 'Periksa kembali isian Anda.', 'error');
                } else {
                    Swal.fire('Error!', 'Terjadi kesalahan sistem.', 'error');
                }
            }
        });
    });
</script>
@endpush

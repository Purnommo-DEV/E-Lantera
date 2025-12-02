@extends('layouts.app')
@section('title', 'Data Warga Posyandu')

@section('content')
<div class="bg-white rounded-2xl shadow-xl overflow-hidden">
    <div class="bg-gradient-to-r from-red-700 to-red-900 text-white p-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-6">
            <div>
                <h3 class="text-3xl font-bold">Data Warga Posyandu</h3>
                <p class="opacity-90 mt-2">RW xx - Dusun Cipulir Estate, Cipadu Jaya, Larangan</p>
            </div>
            <button onclick="addWarga()" class="bg-yellow-400 hover:bg-yellow-500 text-red-900 font-bold py-4 px-8 rounded-xl text-lg shadow-lg transition transform hover:scale-105">
                + Tambah Warga
            </button>
        </div>
    </div>

    <div class="p-8">
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
    <div class="modal-box w-11/12 max-w-4xl">
        <h3 class="text-2xl font-bold mb-6" id="modalTitle">Tambah Warga Baru</h3>
        <form id="wargaForm" class="space-y-6">
            @csrf
            <input type="hidden" id="wargaId">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="label font-semibold">NIK</label>
                    <input type="text" name="nik" maxlength="16" class="input input-bordered w-full" required>
                </div>
                <div>
                    <label class="label font-semibold">Nama Lengkap</label>
                    <input type="text" name="nama" class="input input-bordered w-full" required>
                </div>
                <div>
                    <label class="label font-semibold">Tanggal Lahir</label>
                    <input type="date" name="tanggal_lahir" class="input input-bordered w-full" required>
                </div>
                <div>
                    <label class="label font-semibold">Jenis Kelamin</label>
                    <select name="jenis_kelamin" class="select select-bordered w-full" required>
                        <option value="">Pilih</option>
                        <option value="Laki-laki">Laki-laki</option>
                        <option value="Perempuan">Perempuan</option>
                    </select>
                </div>
                <div>
                    <label class="label font-semibold">Dusun</label>
                    <input type="text" name="dusun" class="input input-bordered w-full" required>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="label font-semibold">RT</label>
                        <input type="text" name="rt" maxlength="3" class="input input-bordered w-full" required>
                    </div>
                    <div>
                        <label class="label font-semibold">RW</label>
                        <input type="text" name="rw" maxlength="3" class="input input-bordered w-full" required>
                    </div>
                </div>
                <div class="md:col-span-2">
                    <label class="label font-semibold">Alamat Lengkap</label>
                    <textarea name="alamat" class="textarea textarea-bordered w-full" rows="2" required></textarea>
                </div>
                <div>
                    <label class="label font-semibold">No. HP</label>
                    <input type="text" name="no_hp" class="input input-bordered w-full">
                </div>
                <div>
                    <label class="label font-semibold">Status Nikah</label>
                    <select name="status_nikah" class="select select-bordered w-full" required>
                        <option value="Menikah">Menikah</option>
                        <option value="Tidak Menikah">Tidak Menikah</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="label font-semibold">Pekerjaan</label>
                    <input type="text" name="pekerjaan" class="input input-bordered w-full">
                </div>
                <div class="md:col-span-2">
                    <label class="label font-semibold">
                        <span class="label-text text-base">Catatan (opsional)</span>
                    </label>
                    <textarea name="catatan" class="textarea textarea-bordered w-full" rows="3" placeholder="Misal: Warga pindahan dari Jakarta, belum punya KTP baru, dll..."></textarea>
                </div>
            </div>

            <div class="modal-action">
                <button type="button" class="btn btn-ghost" onclick="wargaModal.close()">Batal</button>
                <button type="submit" class="btn btn-lg bg-red-600 hover:bg-red-700 text-white border-none">
                    Simpan Warga
                </button>
            </div>
        </form>
    </div>
</dialog>
@endsection

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
            dom: '<"flex justify-between items-center mb-6"lf>rt<"flex justify-between items-center mt-6"ip>',
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
                        <button onclick="editWarga(${d.id})" class="btn btn-warning btn-sm">Edit</button>
                        <button onclick="deleteWarga(${d.id})" class="btn btn-error btn-sm ml-2">Hapus</button>
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
        modal.showModal();
    }

    window.editWarga = function(id) {
        $.get('/warga/' + id, function(data) {
            $('#wargaId').val(data.id);

            // INI YANG PALING PENTING — FORMAT ULANG TANGGAL JADI YYYY-MM-DD
            $('[name="nik"]').val(data.nik);
            $('[name="nama"]').val(data.nama);
            
            // TANGGAL LAHIR → PASTIKAN FORMAT YYYY-MM-DD
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

            // Bersihkan error lama
            $('.text-red-500').remove();
            $('.input-error, .select-error').removeClass('input-error select-error');

            modal.showModal();
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

    // INI YANG PALING PENTING — TANGKAP ERROR VALIDASI DARI LARAVEL!
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
                modal.close();
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
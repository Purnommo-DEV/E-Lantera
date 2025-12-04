@extends('layouts.app')

@section('title', 'Kelola Role & Permission')

@section('content')
<div class="bg-white rounded-2xl shadow-xl overflow-hidden">
    <div class="bg-gradient-to-r from-orange-700 to-orange-900 text-white p-4 md:p-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 md:gap-4">
            <div>
                <h3 class="text-2xl md:text-3xl font-bold">Kelola Role & Permission</h3>
                <p class="opacity-90 mt-1 text-sm md:text-base">Atur hak akses pengguna sistem E-Lantera</p>
            </div>
            <button onclick="addRole()"
                    class="bg-yellow-400 hover:bg-yellow-500 text-orange-900 font-bold py-2 px-4 md:py-2 md:px-5 rounded-md text-sm md:text-base shadow-sm transition transform hover:scale-102">
                Tambah Role
            </button>
        </div>
    </div>

    <div class="p-4 md:p-6">
        <div class="overflow-x-auto">
            <table id="roleTable" class="table table-zebra w-full text-sm">
                <thead class="bg-orange-100 text-orange-900">
                    <tr>
                        <th class="w-36">Nama Role</th>
                        <th>Permission</th>
                        <th class="text-center w-28">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal DaisyUI -->
<dialog id="roleModal" class="modal modal-bottom sm:modal-middle">
    <div class="modal-box w-11/12 max-w-3xl p-4 md:p-5">
        <div class="flex items-start justify-between mb-3">
            <h3 class="text-lg md:text-2xl font-bold" id="modalTitle">Tambah Role Baru</h3>
            <button class="btn btn-sm btn-circle btn-ghost" onclick="roleModal.close()">✕</button>
        </div>

        <form id="roleForm" class="space-y-4">
            @csrf
            <input type="hidden" id="roleId">
            <input type="hidden" id="method" value="POST">

            <div>
                <label class="label font-semibold text-sm">Nama Role</label>
                <input type="text" name="name" placeholder="Contoh: Kader Posyandu" class="input input-bordered w-full input-sm" required>
            </div>

            <div>
                <label class="label font-semibold text-sm">Permission (Hak Akses)</label>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 max-h-72 overflow-y-auto p-3 bg-gray-50 rounded-lg border">
                    @foreach($permissions as $p)
                        <label class="label cursor-pointer justify-start gap-2 items-center text-sm">
                            <input type="checkbox" name="permissions[]" value="{{ $p->name }}" class="checkbox checkbox-primary">
                            <span class="label-text text-sm">{{ $p->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="modal-action pt-2">
                <button type="button" class="btn btn-ghost btn-sm" onclick="roleModal.close()">Batal</button>
                <button type="submit" class="btn bg-orange-600 hover:bg-orange-700 text-white btn-sm">
                    Simpan Role
                </button>
            </div>
        </form>
    </div>
</dialog>
@endsection

@push('styles')
<style>
/* Compact Role page tweaks (matches other compact pages) */

/* DataTables wrapper compact */
#roleTable_wrapper .dataTables_length,
#roleTable_wrapper .dataTables_filter {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-bottom: 0.5rem;
}
#roleTable_wrapper .dataTables_length label,
#roleTable_wrapper .dataTables_filter label {
    margin: 0;
    font-size: 0.78rem;
}
#roleTable_wrapper .dataTables_length select,
#roleTable_wrapper .dataTables_filter input {
    font-size: 0.78rem;
    padding: 0.18rem 0.4rem;
    border-radius: 0.35rem;
}

/* table compact */
#roleTable { font-size: 0.8rem; }
#roleTable thead th { padding: 6px 8px !important; font-size: 0.82rem !important; }
#roleTable tbody td { padding: 6px 8px !important; line-height: 1.12 !important; vertical-align: middle; }

/* action buttons small */
#roleTable .btn, #roleTable button { font-size: 0.72rem !important; padding: 5px 8px !important; border-radius: 0.35rem !important; }

/* modal compact */
.modal-box.w-11\/12.max-w-3xl { max-width: 760px; padding: 0.8rem; }
.input-sm { padding: 0.38rem 0.45rem; font-size: 0.88rem; }

/* badges inside permission list */
.badge { font-size: 0.68rem; padding: 0.18rem 0.4rem; }

/* responsive */
@media (max-width: 640px) {
    #roleTable thead th { font-size: 0.72rem; padding: 5px 6px !important; }
    #roleTable tbody td { font-size: 0.72rem; padding: 5px 6px !important; }
}
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.dataTables.min.css">

<script>
    const modal = document.getElementById('roleModal');
    const form = document.getElementById('roleForm');
    let table;

    $(document).ready(function() {
        table = $('#roleTable').DataTable({
            processing: true,
            serverSide: false,
            ajax: '{{ route('role.data') }}',
            dom: '<"flex justify-between items-center mb-4"lf>rt<"flex justify-between items-center mt-4"ip>',
            language: {
                search: "Cari role:",
                lengthMenu: "Tampilkan _MENU_ role",
                info: "Menampilkan _START_ - _END_ dari _TOTAL_ role",
                processing: "Memuat data..."
            },
            columns: [
                { data: 'name', render: data => `<span class="font-bold text-orange-700 text-sm">${data}</span>` },
                {
                    data: 'permissions',
                    orderable: false,
                    render: function(permissions) {
                        if (!permissions || permissions.length === 0) {
                            return '<span class="text-gray-400 italic text-sm">Tidak ada permission</span>';
                        }
                        return permissions.map(p => {
                            let color = 'badge-info';
                            if (p.includes('create')) color = 'badge-success';
                            if (p.includes('edit')) color = 'badge-warning';
                            if (p.includes('delete')) color = 'badge-error';
                            if (p.includes('view')) color = 'badge-ghost';
                            return `<span class="badge ${color} badge-outline mr-1 mb-1">${p}</span>`;
                        }).join('');
                    }
                },
                {
                    data: null,
                    className: 'text-center whitespace-nowrap',
                    render: function(data) {
                        return `
                            <div class="flex gap-2 justify-center flex-wrap">
                                <button onclick="editRole(${data.id})" class="btn btn-warning btn-sm">Edit</button>
                                <button onclick="deleteRole(${data.id})" class="btn btn-error btn-sm ml-2">Hapus</button>
                            </div>
                        `;
                    }
                }
            ],
            responsive: true
        });
    });

    window.addRole = function() {
        form.reset();
        $('#method').val('POST');
        $('#roleId').val('');
        document.getElementById('modalTitle').textContent = 'Tambah Role Baru';
        if (modal.showModal) modal.showModal(); else modal.classList.add('modal-open');
    }

    window.editRole = function(id) {
        $.get('/role/' + id, function(data) {
            $('[name=name]').val(data.name);
            $('input[name="permissions[]"]').prop('checked', false);
            data.permissions.forEach(p => {
                $(`input[value="${p}"]`).prop('checked', true);
            });
            $('#method').val('PUT');
            $('#roleId').val(id);
            document.getElementById('modalTitle').textContent = 'Edit Role: ' + data.name;
            if (modal.showModal) modal.showModal(); else modal.classList.add('modal-open');
        });
    }

    window.deleteRole = function(id) {
        Swal.fire({
            title: 'Hapus Role Ini?',
            text: "Semua user dengan role ini akan kehilangan hak akses!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then(result => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '/role/' + id,
                    type: 'DELETE',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function() {
                        table.ajax.reload();
                        Swal.fire('Terhapus!', 'Role berhasil dihapus.', 'success');
                    }
                });
            }
        });
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const method = $('#method').val();
        const id = $('#roleId').val();

        let url = '/role';
        if (method === 'PUT') {
            url = '/role/' + id;
            formData.append('_method', 'PUT');
        }

        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function() {
                if (modal.close) modal.close(); else modal.classList.remove('modal-open');
                table.ajax.reload();
                Swal.fire('Sukses!', 'Role berhasil disimpan!', 'success');
            },
            error: function(xhr) {
                Swal.fire('Gagal', xhr.responseJSON?.message || 'Terjadi kesalahan.', 'error');
            }
        });
    });
</script>
@endpush

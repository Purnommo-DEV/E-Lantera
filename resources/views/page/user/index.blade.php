@extends('layouts.app')
@section('title', 'Kelola User')

@section('content')
<div class="bg-white rounded-2xl shadow-xl overflow-hidden">
    <div class="bg-gradient-to-r from-amber-600 to-amber-800 text-white p-3 md:p-5">
        <div class="flex justify-between items-center">
            <h3 class="text-lg md:text-2xl font-bold">Kelola User</h3>
            <button onclick="addUser()" class="bg-yellow-400 hover:bg-yellow-500 text-amber-900 font-bold py-2 px-4 rounded-md text-sm md:text-base shadow-sm transition">
                Tambah User
            </button>
        </div>
    </div>

    <div class="p-3 md:p-5">
        <div class="overflow-x-auto">
            <table id="userTable" class="table table-zebra w-full text-sm">
                <thead class="bg-amber-100 text-amber-900">
                    <tr>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th class="text-center w-28">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal DaisyUI -->
<dialog id="userModal" class="modal">
    <div class="modal-box w-11/12 max-w-2xl p-4 md:p-5">
        <div class="flex items-start justify-between mb-2">
            <h3 class="text-lg md:text-2xl font-bold" id="modalTitle">Tambah User</h3>
            <button class="btn btn-sm btn-circle btn-ghost" onclick="userModal.close()">✕</button>
        </div>

        <form id="userForm" class="space-y-3">
            @csrf
            <input type="hidden" id="userId">
            <input type="hidden" id="method" value="POST">

            <div>
                <label class="label text-sm">Nama</label>
                <input type="text" name="name" class="input input-bordered w-full input-sm" required>
            </div>
            <div>
                <label class="label text-sm">Email</label>
                <input type="email" name="email" class="input input-bordered w-full input-sm" required>
            </div>
            <div>
                <label class="label text-sm">Password <span class="text-xs text-gray-500">(kosongkan jika edit)</span></label>
                <input type="password" name="password" id="passwordField" class="input input-bordered w-full input-sm">
            </div>
            <div>
                <label class="label text-sm">Role</label>
                <select name="roles[]" class="select select-bordered w-full select-sm" multiple required>
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}">{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="modal-action pt-2">
                <button type="button" class="btn btn-ghost btn-sm" onclick="userModal.close()">Batal</button>
                <button type="submit" class="btn btn-primary bg-amber-600 hover:bg-amber-700 btn-sm">Simpan</button>
            </div>
        </form>
    </div>
</dialog>
@endsection

@push('styles')
<style>
/* Compact User page tweaks */

/* DataTables wrapper compact */
#userTable_wrapper .dataTables_length,
#userTable_wrapper .dataTables_filter {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-bottom: 0.5rem;
}
#userTable_wrapper .dataTables_length label,
#userTable_wrapper .dataTables_filter label {
    margin: 0;
    font-size: 0.78rem;
}
#userTable_wrapper .dataTables_length select,
#userTable_wrapper .dataTables_filter input {
    font-size: 0.78rem;
    padding: 0.18rem 0.4rem;
    border-radius: 0.35rem;
}

/* table compact */
#userTable { font-size: 0.8rem; }
#userTable thead th { padding: 6px 8px !important; font-size: 0.82rem !important; }
#userTable tbody td { padding: 6px 8px !important; line-height: 1.12 !important; vertical-align: middle; }

/* action buttons small */
#userTable .btn, #userTable button { font-size: 0.72rem !important; padding: 5px 8px !important; border-radius: 0.35rem !important; }

/* modal compact */
.modal-box.w-11\/12.max-w-2xl { max-width: 700px; padding: 0.8rem; }
.input-sm, .select-sm { padding: 0.38rem 0.45rem; font-size: 0.88rem; }

/* badges inside roles */
.badge { font-size: 0.68rem; padding: 0.18rem 0.4rem; }

@media (max-width: 640px) {
    #userTable thead th { font-size: 0.72rem; padding: 5px 6px !important; }
    #userTable tbody td { font-size: 0.72rem; padding: 5px 6px !important; }
}
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.dataTables.min.css">

<script>
    const modal = document.getElementById('userModal');
    const form = document.getElementById('userForm');
    let table;

    $(document).ready(function() {
        table = $('#userTable').DataTable({
            processing: true,
            serverSide: false,
            ajax: '{{ route('user.data') }}',
            dom: '<"flex justify-between items-center mb-4"lf>rt<"flex justify-between items-center mt-4"ip>',
            language: {
                processing: "Memuat data...",
                search: "Cari:",
                lengthMenu: "Tampilkan _MENU_ data",
                info: "Menampilkan _START_ - _END_ dari _TOTAL_",
                paginate: { previous: "‹", next: "›" }
            },
            columns: [
                { data: 'name' },
                { data: 'email' },
                {
                    data: 'roles',
                    render: function(data) {
                        if (!data || data.length === 0) return '<span class="badge badge-ghost text-sm">Tidak ada role</span>';
                        return data.map(role => `<span class="badge badge-primary badge-outline mr-1 text-sm">${role}</span>`).join('');
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    render: function(data) {
                        return `
                            <div class="flex gap-2 justify-center flex-wrap">
                                <button onclick="editUser(${data.id})" class="btn btn-warning btn-sm">Edit</button>
                                <button onclick="deleteUser(${data.id})" class="btn btn-error btn-sm ml-2">Hapus</button>
                            </div>
                        `;
                    }
                }
            ],
            responsive: true
        });
    });

    window.addUser = function() {
        form.reset();
        $('#method').val('POST');
        $('#userId').val('');
        document.getElementById('modalTitle').textContent = 'Tambah User';
        document.getElementById('passwordField').required = true;
        if (modal.showModal) modal.showModal(); else modal.classList.add('modal-open');
    }

    window.editUser = function(id) {
        $.get('/user/' + id, function(data) {
            $('[name=name]').val(data.name);
            $('[name=email]').val(data.email);
            $('select[name="roles[]"]').val(data.roles).trigger('change');
            $('#method').val('PUT');
            $('#userId').val(id);
            document.getElementById('modalTitle').textContent = 'Edit User';
            document.getElementById('passwordField').required = false;
            if (modal.showModal) modal.showModal(); else modal.classList.add('modal-open');
        });
    }

    window.deleteUser = function(id) {
        Swal.fire({
            title: 'Yakin hapus?',
            text: "User akan dihapus permanen!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then(result => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '/user/' + id,
                    type: 'DELETE',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function() {
                        table.ajax.reload();
                        Swal.fire('Terhapus!', 'User telah dihapus.', 'success');
                    }
                });
            }
        });
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const method = $('#method').val();
        const id = $('#userId').val();

        let url = '/user';
        if (method === 'PUT') {
            url = '/user/' + id;
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
                Swal.fire('Sukses!', 'User berhasil disimpan.', 'success');
            },
            error: function(xhr) {
                Swal.fire('Error', xhr.responseJSON?.message || 'Gagal menyimpan.', 'error');
            }
        });
    });
</script>
@endpush

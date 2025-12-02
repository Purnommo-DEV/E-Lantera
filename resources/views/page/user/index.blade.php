@extends('layouts.app')
@section('title', 'Kelola User')

@section('content')
<div class="bg-white rounded-2xl shadow-xl overflow-hidden">
    <div class="bg-gradient-to-r from-amber-600 to-amber-800 text-white p-6">
        <div class="flex justify-between items-center">
            <h3 class="text-2xl font-bold">Kelola User</h3>
            <button onclick="addUser()" class="bg-yellow-400 hover:bg-yellow-500 text-amber-900 font-bold py-3 px-6 rounded-xl transition">
                Tambah User
            </button>
        </div>
    </div>

    <div class="p-6">
        <table id="userTable" class="table table-zebra w-full">
            <thead class="bg-amber-100 text-amber-900">
                <tr>
                    <th>Nama</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- Modal DaisyUI -->
<dialog id="userModal" class="modal">
    <div class="modal-box w-11/12 max-w-2xl">
        <h3 class="text-2xl font-bold mb-6" id="modalTitle">Tambah User</h3>
        <form id="userForm" class="space-y-5">
            @csrf
            <input type="hidden" id="userId">
            <input type="hidden" id="method" value="POST">

            <div>
                <label class="label">Nama</label>
                <input type="text" name="name" class="input input-bordered w-full" required>
            </div>
            <div>
                <label class="label">Email</label>
                <input type="email" name="email" class="input input-bordered w-full" required>
            </div>
            <div>
                <label class="label">
                    Password <span class="text-xs text-gray-500">(kosongkan jika edit)</span>
                </label>
                <input type="password" name="password" id="passwordField" class="input input-bordered w-full">
            </div>
            <div>
                <label class="label">Role</label>
                <select name="roles[]" class="select select-bordered w-full" multiple required>
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}">{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="modal-action">
                <button type="button" class="btn btn-ghost" onclick="userModal.close()">Batal</button>
                <button type="submit" class="btn btn-primary bg-amber-600 hover:bg-amber-700">Simpan</button>
            </div>
        </form>
    </div>
</dialog>
@endsection

@push('scripts')
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
                paginate: {
                    previous: "‹",
                    next: "›"
                }
            },
            columns: [
                { data: 'name' },
                { data: 'email' },
                {
                    data: 'roles',
                    render: function(data) {
                        if (!data || data.length === 0) return '<span class="badge badge-ghost">Tidak ada role</span>';
                        return data.map(role => `<span class="badge badge-primary badge-outline mr-1">${role}</span>`).join('');
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    render: function(data) {
                        return `
                            <button onclick="editUser(${data.id})" class="btn btn-warning btn-sm">Edit</button>
                            <button onclick="deleteUser(${data.id})" class="btn btn-error btn-sm ml-2">Hapus</button>
                        `;
                    }
                }
            ]
        });
    });

    window.addUser = function() {
        form.reset();
        $('#method').val('POST');
        $('#userId').val('');
        document.getElementById('modalTitle').textContent = 'Tambah User';
        document.getElementById('passwordField').required = true;
        modal.showModal();
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
            modal.showModal();
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
                modal.close();
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
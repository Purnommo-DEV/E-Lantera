{{-- resources/views/page/role/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Kelola Role & Permission')

@section('content')
<div class="bg-white rounded-2xl shadow-xl overflow-hidden">
    <div class="bg-gradient-to-r from-orange-700 to-orange-900 text-white p-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h3 class="text-3xl font-bold">Kelola Role & Permission</h3>
                <p class="opacity-90 mt-2">Atur hak akses pengguna sistem E-Lantera</p>
            </div>
            <button onclick="addRole()" 
                    class="bg-yellow-400 hover:bg-yellow-500 text-orange-900 font-bold py-4 px-8 rounded-xl text-lg shadow-lg transition transform hover:scale-105">
                Tambah Role
            </button>
        </div>
    </div>

    <div class="p-8">
        <div class="overflow-x-auto">
            <table id="roleTable" class="table table-zebra w-full text-sm">
                <thead class="bg-orange-100 text-orange-900">
                    <tr>
                        <th class="w-32">Nama Role</th>
                        <th>Permission</th>
                        <th class="text-center w-32">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal DaisyUI -->
<dialog id="roleModal" class="modal modal-bottom sm:modal-middle">
    <div class="modal-box w-11/12 max-w-4xl">
        <h3 class="text-2xl font-bold mb-6" id="modalTitle">Tambah Role Baru</h3>

        <form id="roleForm" class="space-y-6">
            @csrf
            <input type="hidden" id="roleId">
            <input type="hidden" id="method" value="POST">

            <div>
                <label class="label font-semibold">
                    <span class="label-text text-base">Nama Role</span>
                </label>
                <input type="text" name="name" placeholder="Contoh: Kader Posyandu" class="input input-bordered w-full" required>
            </div>

            <div>
                <label class="label font-semibold">
                    <span class="label-text text-base">Permission (Hak Akses)</span>
                </label>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 max-h-96 overflow-y-auto p-4 bg-gray-50 rounded-xl border">
                    @foreach($permissions as $p)
                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="checkbox" name="permissions[]" value="{{ $p->name }}" class="checkbox checkbox-primary">
                            <span class="label-text">{{ $p->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="modal-action">
                <button type="button" class="btn btn-ghost" onclick="roleModal.close()">Batal</button>
                <button type="submit" class="btn btn-lg bg-orange-600 hover:bg-orange-700 text-white border-none">
                    Simpan Role
                </button>
            </div>
        </form>
    </div>
</dialog>
@endsection

@push('scripts')
<script>
    const modal = document.getElementById('roleModal');
    const form = document.getElementById('roleForm');
    let table;

    $(document).ready(function() {
        table = $('#roleTable').DataTable({
            processing: true,
            serverSide: false,
            ajax: '{{ route('role.data') }}',  // SESUAI ROUTE ANDA: /role/data
            dom: '<"flex justify-between items-center mb-6"lf>rt<"flex justify-between items-center mt-6"ip>',
            language: {
                search: "Cari role:",
                lengthMenu: "Tampilkan _MENU_ role",
                info: "Menampilkan _START_ - _END_ dari _TOTAL_ role",
                processing: "Memuat data..."
            },
            columns: [
                { 
                    data: 'name',
                    render: data => `<span class="font-bold text-orange-700">${data}</span>`
                },
                {
                    data: 'permissions',
                    orderable: false,
                    render: function(permissions) {
                        if (!permissions || permissions.length === 0) {
                            return '<span class="text-gray-400 italic">Tidak ada permission</span>';
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
                            <button onclick="editRole(${data.id})" class="btn btn-warning btn-sm">Edit</button>
                            <button onclick="deleteRole(${data.id})" class="btn btn-error btn-sm ml-2">Hapus</button>
                        `;
                    }
                }
            ]
        });
    });

    window.addRole = function() {
        form.reset();
        $('#method').val('POST');
        $('#roleId').val('');
        document.getElementById('modalTitle').textContent = 'Tambah Role Baru';
        modal.showModal();
    }

    window.editRole = function(id) {
        $.get('/role/' + id, function(data) {  // URL: /role/{id}
            $('[name=name]').val(data.name);
            $('input[name="permissions[]"]').prop('checked', false);
            data.permissions.forEach(p => {
                $(`input[value="${p}"]`).prop('checked', true);
            });
            $('#method').val('PUT');
            $('#roleId').val(id);
            document.getElementById('modalTitle').textContent = 'Edit Role: ' + data.name;
            modal.showModal();
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
                modal.close();
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
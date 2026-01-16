@extends('layouts.app')
@section('title', 'Data Warga Posyandu')

@section('content')
<div class="bg-white rounded-2xl shadow-xl overflow-hidden">
    <!-- Header -->
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

    <!-- Table Utama (Data Online) -->
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

    <!-- Bagian Pending Sync (Offline) -->
    <div id="pendingSection" class="mt-0 border-t border-gray-200 bg-yellow-50 p-4 md:p-6 shadow-inner">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-yellow-800">Data Menunggu Sinkronisasi (Offline)</h3>
            <span id="pendingCount" class="badge badge-warning">0 item</span>
        </div>
        <div class="overflow-x-auto">
            <table id="pendingTable" class="table table-zebra table-sm w-full text-sm">
                <thead class="bg-yellow-100 text-yellow-900">
                    <tr>
                        <th>NIK</th>
                        <th>Nama</th>
                        <th>Action</th>
                        <th>Waktu</th>
                        <th>Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody id="pendingBody">
                    <!-- Akan diisi via JS -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<dialog id="wargaModal" class="modal modal-bottom sm:modal-middle">
    <div class="modal-box w-11/12 max-w-4xl p-0 max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between px-6 py-4 bg-gradient-to-r from-red-600 to-red-500 text-white shrink-0">
            <h3 class="text-lg md:text-xl font-bold" id="modalTitle">Tambah Warga Baru</h3>
            <button class="btn btn-sm btn-circle btn-ghost text-white" onclick="wargaModal.close()">✕</button>
        </div>

        <form id="wargaForm" class="flex-1 overflow-y-auto px-6 py-5 space-y-5">
            @csrf
            <input type="hidden" id="wargaId">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="form-control">
                    <label class="label font-medium">NIK</label>
                    <input type="text" name="nik" maxlength="16" data-autotab required class="input input-bordered w-full rounded-xl">
                    <p class="error-message text-red-500 text-sm mt-1 hidden"></p>
                </div>

                <div class="form-control">
                    <label class="label font-medium">Nama Lengkap</label>
                    <input type="text" name="nama" required class="input input-bordered w-full rounded-xl">
                    <p class="error-message text-red-500 text-sm mt-1 hidden"></p>
                </div>

                <div class="form-control">
                    <label class="label font-medium">Tanggal Lahir</label>
                    <input type="date" name="tanggal_lahir" required class="input input-bordered w-full rounded-xl">
                    <p class="error-message text-red-500 text-sm mt-1 hidden"></p>
                </div>

                <div class="form-control">
                    <label class="label font-medium">Jenis Kelamin</label>
                    <select name="jenis_kelamin" required class="select select-bordered w-full rounded-xl">
                        <option value="">Pilih</option>
                        <option>Laki-laki</option>
                        <option>Perempuan</option>
                    </select>
                    <p class="error-message text-red-500 text-sm mt-1 hidden"></p>
                </div>

                <div class="form-control">
                    <label class="label font-medium">Dusun</label>
                    <input type="text" name="dusun" required class="input input-bordered w-full rounded-xl">
                    <p class="error-message text-red-500 text-sm mt-1 hidden"></p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="form-control">
                        <label class="label font-medium">RT</label>
                        <input type="text" name="rt" maxlength="3" data-autotab required class="input input-bordered w-full rounded-xl">
                        <p class="error-message text-red-500 text-sm mt-1 hidden"></p>
                    </div>
                    <div class="form-control">
                        <label class="label font-medium">RW</label>
                        <input type="text" name="rw" maxlength="3" data-autotab required class="input input-bordered w-full rounded-xl">
                        <p class="error-message text-red-500 text-sm mt-1 hidden"></p>
                    </div>
                </div>

                <div class="form-control md:col-span-2">
                    <label class="label font-medium">Alamat</label>
                    <textarea name="alamat" rows="2" required class="textarea textarea-bordered w-full rounded-xl"></textarea>
                    <p class="error-message text-red-500 text-sm mt-1 hidden"></p>
                </div>

                <div class="form-control">
                    <label class="label font-medium">No HP</label>
                    <input type="text" name="no_hp" class="input input-bordered w-full rounded-xl">
                    <p class="error-message text-red-500 text-sm mt-1 hidden"></p>
                </div>

                <div class="form-control">
                    <label class="label font-medium">Status Nikah</label>
                    <select name="status_nikah" required class="select select-bordered w-full rounded-xl">
                        <option>Menikah</option>
                        <option>Tidak Menikah</option>
                    </select>
                    <p class="error-message text-red-500 text-sm mt-1 hidden"></p>
                </div>

                <div class="form-control md:col-span-2">
                    <label class="label font-medium">Pekerjaan</label>
                    <input type="text" name="pekerjaan" class="input input-bordered w-full rounded-xl">
                    <p class="error-message text-red-500 text-sm mt-1 hidden"></p>
                </div>

                <div class="form-control md:col-span-2">
                    <label class="label font-medium">Catatan</label>
                    <textarea name="catatan" rows="3" class="textarea textarea-bordered w-full rounded-xl"></textarea>
                    <p class="error-message text-red-500 text-sm mt-1 hidden"></p>
                </div>
            </div>
        </form>

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
    .input-error, .select-error, .textarea-error { border-color: #ef4444 !important; }
    .btn-loading { pointer-events: none; opacity: 0.8; }
    .form-control.error .label {
        color: #ef4444;
        font-weight: 600;
    }
    .form-control.error .error-message {
        display: block !important;   /* force tampil kalau perlu */
    }
    .pending-row {
        background-color: #fefce8; /* kuning sangat muda */
    }
    .pending-row.success {
        background-color: #dcfce7 !important;
        transition: background-color 0.8s ease;
    }
    .pending-row.failed {
        background-color: #fee2e2 !important;
    }
    /* sisanya style kamu tetap sama, boleh copy dari sebelumnya */
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.dataTables.min.css">
<script src="https://cdn.jsdelivr.net/npm/dexie@3/dist/dexie.min.js"></script>

<script>
    const db = new Dexie('ELanteraOfflineDB');
    db.version(1).stores({ pendingWarga: '++id, action, data, timestamp' });

    document.getElementById('btnViewPending')?.addEventListener('click', async () => {
        const pending = await db.pendingWarga.toArray();
        const listEl = document.getElementById('pendingItems');
        const container = document.getElementById('pendingList');

        listEl.innerHTML = '';

        if (pending.length === 0) {
            listEl.innerHTML = '<li class="text-gray-500">Tidak ada data pending.</li>';
        } else {
            pending.forEach(item => {
                const data = JSON.parse(item.data);
                const li = document.createElement('li');
                li.innerHTML = `
                    <strong>${data.nama || '(nama kosong)'}</strong> 
                    — NIK: ${data.nik || '-'} 
                    — ${item.action.toUpperCase()} 
                    — ${new Date(item.timestamp).toLocaleString('id-ID')}
                    <button class="btn btn-xs btn-error ml-2 delete-pending" data-id="${item.id}">Hapus</button>
                `;
                listEl.appendChild(li);
            });
        }

        container.classList.remove('hidden');
    });

    // Bonus: tombol hapus pending (opsional, hati-hati!)
    document.addEventListener('click', async e => {
        if (e.target.classList.contains('delete-pending')) {
            const id = parseInt(e.target.dataset.id);
            if (confirm('Hapus data pending ini?')) {
                await db.pendingWarga.delete(id);
                e.target.closest('li').remove();
            }
        }
    });

    const modal = document.getElementById('wargaModal');
    const form = document.getElementById('wargaForm');
    const btnSubmit = document.getElementById('btnSubmitWarga');

    let table; // global untuk DataTable

    function startLoading() {
        btnSubmit.classList.add('btn-loading');
        btnSubmit.disabled = true;
        btnSubmit.querySelector('.btn-text').textContent = 'Menyimpan...';
        btnSubmit.querySelector('.btn-spinner').classList.remove('hidden');
    }

    function stopLoading() {
        btnSubmit.classList.remove('btn-loading');
        btnSubmit.disabled = false;
        btnSubmit.querySelector('.btn-text').textContent = 'Simpan Warga';
        btnSubmit.querySelector('.btn-spinner').classList.add('hidden');
    }

    async function saveOffline(action, data) {
        await db.pendingWarga.add({
            action,
            data: JSON.stringify(data),
            timestamp: new Date().toISOString()
        });
        console.log('Data disimpan offline:', action);
    }

    async function syncPendingWarga() {
        const pending = await db.pendingWarga.toArray();
        if (!pending.length) return;

        let successCount = 0;
        let validationFailed = [];

        for (const item of pending) {
            const data = JSON.parse(item.data);
            const formData = new FormData();
            Object.entries(data).forEach(([k, v]) => formData.append(k, v));

            try {
                const res = await fetch(
                    item.action === 'store' ? '/warga' : `/warga/${data.id || ''}`,
                    {
                        method: item.action === 'store' ? 'POST' : 'PUT',
                        body: formData,
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }
                );

                if (res.ok) {
                    // Tampilkan sukses dulu di UI sebelum hapus
                    const row = document.querySelector(`tr[data-pending-id="${item.id}"]`);
                    if (row) {
                        const statusCell = row.querySelector('td:nth-child(5)');
                        statusCell.innerHTML = '<span class="badge badge-success animate-pulse">Sukses!</span>';
                        row.classList.add('success');
                    }

                    // Tunggu sedikit biar user lihat animasi (1 detik)
                    await new Promise(resolve => setTimeout(resolve, 1200));

                    // Baru hapus dari DB
                    await db.pendingWarga.delete(item.id);
                    successCount++;
                } else if (res.status === 422) {
                    const err = await res.json();
                    await db.pendingWarga.delete(item.id); // hapus karena invalid
                    validationFailed.push({
                        nama: data.nama || data.nik || 'Data',
                        errors: err.errors || {}
                    });
                }
                // error lain → biarkan tetap pending

            } catch (err) {
                console.error('Sync error:', err);
                // biarkan tetap pending
            }
        }

        // Refresh table pending setelah semua proses
        await refreshPendingTable();

        if (successCount > 0) {
            Swal.fire({
                title: 'Sinkronisasi Berhasil',
                text: `${successCount} data berhasil dikirim ke server.`,
                icon: 'success',
                timer: 3000
            });
            table?.ajax.reload(null, false); // refresh table utama
        }

        if (validationFailed.length > 0) {
            let msg = validationFailed.map(f => 
                `• ${f.nama}: ${Object.values(f.errors).flat().join(', ')}`
            ).join('\n');
            Swal.fire({
                title: 'Beberapa Data Gagal Validasi',
                html: msg.replace(/\n/g, '<br>'),
                icon: 'warning'
            });
        }
    }

    // Fungsi untuk refresh tampilan pending
    async function refreshPendingTable(showSuccessAnimation = false) {
        const pending = await db.pendingWarga.toArray();
        const tbody = document.getElementById('pendingBody');
        const countEl = document.getElementById('pendingCount');
        const section = document.getElementById('pendingSection');

        tbody.innerHTML = '';

        if (pending.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-gray-500">Tidak ada data pending.</td></tr>';
            countEl.textContent = '0 item';
            countEl.classList.add('badge-neutral');
            countEl.classList.remove('badge-warning');
            return;
        }

        countEl.textContent = `${pending.length} item`;
        countEl.classList.remove('badge-neutral');
        countEl.classList.add('badge-warning');

        pending.forEach(item => {
            const data = JSON.parse(item.data);
            const row = document.createElement('tr');
            row.classList.add('pending-row');
            row.dataset.pendingId = item.id; // untuk referensi nanti

            row.innerHTML = `
                <td>${data.nik || '-'}</td>
                <td class="font-medium">${data.nama || '(tanpa nama)'}</td>
                <td>${item.action === 'store' ? 'Tambah Baru' : 'Update'}</td>
                <td>${new Date(item.timestamp).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' })}</td>
                <td><span class="badge badge-outline badge-info">Menunggu</span></td>
                <td class="text-center">
                    <button class="btn btn-xs btn-error delete-pending" data-id="${item.id}">Hapus</button>
                </td>
            `;
            tbody.appendChild(row);
        });

        // Event listener hapus (bisa di luar loop, pakai event delegation)
        document.querySelectorAll('.delete-pending').forEach(btn => {
            btn.addEventListener('click', async e => {
                const id = parseInt(e.target.dataset.id);
                if (confirm('Hapus data pending ini? Tidak bisa dikembalikan.')) {
                    await db.pendingWarga.delete(id);
                    refreshPendingTable();
                }
            });
        });

        if (pending.length === 0) {
            section.classList.add('hidden');
        } else {
            section.classList.remove('hidden');
        }

    }

    // Panggil awal saat load halaman
    refreshPendingTable();

    // Update saat sync selesai (akan dipanggil dari syncPendingWarga)

    // DataTable
    $(document).ready(function () {
        table = $('#wargaTable').DataTable({
            processing: true,
            serverSide: false,
            order: [],
            ajax: "{{ route('warga.data') }}",
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

    function vibrateError() {
        if (navigator.vibrate) navigator.vibrate([100, 50, 100]);
    }

    function clearFieldError(input) {
        const formControl = input.closest('.form-control');
        if (!formControl) return;
        const errorEl = formControl.querySelector('.error-message');
        if (errorEl) {
            errorEl.textContent = '';
            errorEl.classList.add('hidden');
        }
        input.classList.remove('input-error', 'select-error', 'textarea-error');
    }

    window.addWarga = function () {
        form.reset();
        document.getElementById('wargaId').value = '';
        document.getElementById('modalTitle').textContent = 'Tambah Warga Baru';
        document.querySelectorAll('.error-message').forEach(el => {
            el.textContent = '';
            el.classList.add('hidden');
        });
        document.querySelectorAll('input, select, textarea').forEach(el => {
            el.classList.remove('input-error', 'select-error', 'textarea-error');
        });
        modal.showModal();
    };

    window.editWarga = function (id) {
        fetch(`/warga/${id}`, { headers: { 'Accept': 'application/json' } })
            .then(res => res.json())
            .then(data => {
                form.reset();
                document.getElementById('wargaId').value = data.id;
                Object.keys(data).forEach(key => {
                    const field = document.querySelector(`[name="${key}"]`);
                    if (field && key !== 'tanggal_lahir') field.value = data[key];
                });
                document.getElementById('modalTitle').textContent = `Edit: ${data.nama}`;
                document.querySelectorAll('.error-message').forEach(el => el.classList.add('hidden'));
                document.querySelectorAll('input, select, textarea').forEach(el => el.classList.remove('input-error', 'select-error', 'textarea-error'));
                modal.showModal();

                // tanggal lahir
                const tglField = document.querySelector('input[name="tanggal_lahir"]');
                if (data.tanggal_lahir) tglField.value = data.tanggal_lahir;
            })
            .catch(() => Swal.fire('Error', 'Gagal memuat data', 'error'));
    };

    window.deleteWarga = function (id) {
        Swal.fire({
            title: 'Hapus warga?',
            text: 'Data akan dihapus permanen',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, hapus'
        }).then(r => {
            if (r.isConfirmed) {
                fetch(`/warga/${id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                })
                .then(res => {
                    if (res.ok) {
                        table.ajax.reload();
                        Swal.fire('Terhapus', 'Data berhasil dihapus', 'success');
                    } else {
                        Swal.fire('Error', 'Gagal menghapus', 'error');
                    }
                });
            }
        });
    };

    // Clear error real-time
    form.addEventListener('input', e => {
        if (e.target.matches('input, select, textarea')) clearFieldError(e.target);
    });

    // Auto-tab
    function focusNextField(current) {
        const fields = Array.from(form.querySelectorAll('input, select, textarea')).filter(el => el.offsetParent !== null && !el.disabled && !el.readOnly);
        const idx = fields.indexOf(current);
        if (idx > -1 && idx + 1 < fields.length) fields[idx + 1].focus();
    }

    form.addEventListener('input', e => {
        const input = e.target;
        if (!input.hasAttribute('data-autotab')) return;
        if (e.inputType === 'deleteContentBackward') return;

        const max = input.getAttribute('maxlength');
        if (max && input.value.length >= max) {
            setTimeout(() => focusNextField(input), 80);
        }
    });

    // Hanya angka untuk RT/RW
    form.querySelectorAll('input[name="rt"], input[name="rw"]').forEach(el => {
        el.addEventListener('input', () => {
            el.value = el.value.replace(/\D/g, '');
        });
    });

    // SUBMIT utama
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        startLoading();

        // ────────────────────────────────────────────────
        // 1. Reset semua error state dulu
        // ────────────────────────────────────────────────
        const errorElements = form.querySelectorAll('.error-message');
        const inputs = form.querySelectorAll('input, select, textarea');

        errorElements.forEach(el => {
            el.textContent = '';
            el.classList.add('hidden');
        });

        inputs.forEach(el => {
            el.classList.remove('input-error', 'select-error', 'textarea-error');
            el.closest('.form-control')?.classList.remove('error');
        });

        // ────────────────────────────────────────────────
        // 2. Siapkan data
        // ────────────────────────────────────────────────
        const id = document.getElementById('wargaId').value.trim();
        const url = id ? `/warga/${id}` : '/warga';
        const formData = new FormData(form);
        if (id) formData.append('_method', 'PUT');

        const plainData = Object.fromEntries(formData.entries());

        // ────────────────────────────────────────────────
        // 3. Mode Offline
        // ────────────────────────────────────────────────
        if (!navigator.onLine) {
            await saveOffline(id ? 'update' : 'store', plainData);
            stopLoading();
            Swal.fire({
                title: 'Mode Offline',
                text: 'Data disimpan lokal dan akan dikirim otomatis saat online.',
                icon: 'info'
            });
            modal.close();
            await refreshPendingTable();
            return;
        }

        try {
            const response = await fetch(url, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            // ────────────────────────────────────────────────
            // 4. Tangani VALIDATION ERROR (status 422)
            // ────────────────────────────────────────────────
            if (response.status === 422) {
                const result = await response.json();
                const errors = result.errors || {};

                let firstInvalidField = null;

                Object.entries(errors).forEach(([field, messages]) => {
                    // Support dotted notation & array (misal children.*.nama)
                    const input = form.querySelector(`[name="${field}"], [name="${field}[]"]`);
                    if (!input) return;

                    const formControl = input.closest('.form-control');
                    if (!formControl) return;

                    const errorEl = formControl.querySelector('.error-message');
                    if (!errorEl) return;

                    // Ambil pesan pertama saja (paling umum)
                    errorEl.textContent = messages[0] || 'Field ini tidak valid';
                    errorEl.classList.remove('hidden');

                    // Tambah class error ke input & form-control
                    if (input.tagName === 'SELECT') {
                        input.classList.add('select-error');
                    } else if (input.tagName === 'TEXTAREA') {
                        input.classList.add('textarea-error');
                    } else {
                        input.classList.add('input-error');
                    }
                    formControl.classList.add('error');

                    if (!firstInvalidField) firstInvalidField = input;
                });

                if (firstInvalidField) {
                    firstInvalidField.focus();
                    firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    vibrateError();
                }

                stopLoading();
                return; // ← penting, jangan lanjut ke success
            }

            // ────────────────────────────────────────────────
            // 5. Server error lain (bukan 422)
            // ────────────────────────────────────────────────
            if (!response.ok) {
                let message = `Error ${response.status}`;
                try {
                    const err = await response.json();
                    message = err.message || message;
                } catch {}
                throw new Error(message);
            }

            // ────────────────────────────────────────────────
            // 6. SUCCESS
            // ────────────────────────────────────────────────
            const result = await response.json();
            stopLoading();
            modal.close();
            table.ajax.reload(null, false); // false = tidak reset paging
            Swal.fire('Berhasil', result.message || 'Data tersimpan', 'success');

        } catch (err) {
            stopLoading();
            console.error(err);

            if (!navigator.onLine) {
                await saveOffline(id ? 'update' : 'store', plainData);
                Swal.fire({
                    title: 'Koneksi Terputus',
                    text: 'Data disimpan lokal. Akan sync saat online kembali.',
                    icon: 'warning'
                });
                modal.close();
                return;
            }

            Swal.fire('Gagal', err.message || 'Terjadi kesalahan server', 'error');
        }
    });
    // Sync saat online
    window.addEventListener('online', syncPendingWarga);
    if (navigator.onLine) {
    syncPendingWarga();                 // sync jika online
    } else {
        refreshPendingTable();              // tampilkan pending jika offline
    }

    // Offline badge
    const offlineBadge = document.createElement('div');
    offlineBadge.id = 'offline-badge';
    offlineBadge.className = 'fixed top-4 right-4 z-50 badge badge-warning gap-2 hidden';
    offlineBadge.innerHTML = `<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg> Offline`;
    document.body.appendChild(offlineBadge);

    window.addEventListener('online', () => offlineBadge.classList.add('hidden'));
    window.addEventListener('offline', () => offlineBadge.classList.remove('hidden'));
</script>
@endpush
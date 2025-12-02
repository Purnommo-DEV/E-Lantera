<div class="flex gap-3 justify-center">
    <button onclick="window.modal.openEdit({{ $r->id }})"
            class="bg-yellow-500 hover:bg-yellow-600 text-white px-5 py-2 rounded-lg font-bold">Edit</button>
    <button onclick="if(confirm('Yakin hapus?')) axios.delete('/lansia/{{ $r->id }}').then(()=>$('#table-lansia').DataTable().ajax.reload())"
            class="bg-red-500 hover:bg-red-600 text-white px-5 py-2 rounded-lg font-bold">Hapus</button>
</div>
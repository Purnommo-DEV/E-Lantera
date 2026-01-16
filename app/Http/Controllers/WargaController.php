<?php
namespace App\Http\Controllers;

use App\Models\Warga;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WargaController extends Controller
{
    public function index()
    {
        return view('page.warga.index');
    }

    public function data()
    {
        $warga = Warga::select([
                'id', 'nik', 'nama', 'tanggal_lahir', 'jenis_kelamin',
                'alamat', 'dusun', 'rt', 'rw', 'no_hp', 'status_nikah', 'pekerjaan', 'catatan'
            ])
            ->orderByDesc('id') // 🔥 DATA TERBARU DI ATAS
            ->get()
            ->map(function ($w) {

                $lahir = \Carbon\Carbon::parse($w->tanggal_lahir);
                $diff  = $lahir->diff(now());

                $tahun = $diff->y;
                $bulan = $diff->m;

                return [
                    'id'            => $w->id,
                    'nik'           => $w->nik ?: '-',
                    'nama'          => $w->nama,
                    'umur'          => $tahun > 0 ? "$tahun thn $bulan bln" : "$bulan bln",
                    'jenis_kelamin' => $w->jenis_kelamin,
                    'alamat'        => "Dusun {$w->dusun} RT {$w->rt}/RW {$w->rw}",
                    'no_hp'         => $w->no_hp ?: '-',
                    'status_nikah'  => $w->status_nikah,
                    'pekerjaan'     => $w->pekerjaan ?: '-',
                    'catatan'       => $w->catatan
                        ? '<small class="text-gray-600 italic">Catatan: '
                            . \Illuminate\Support\Str::limit($w->catatan, 60)
                            . '</small>'
                        : '-',
                ];
            });

        return response()->json(['data' => $warga]);
    }

    public function show($id)
    {
        $warga = Warga::findOrFail($id);

        return response()->json([
            'id'             => $warga->id,
            'nik'            => $warga->nik,
            'nama'           => $warga->nama,
            'tanggal_lahir'  => optional($warga->tanggal_lahir)->format('Y-m-d'),
            'jenis_kelamin'  => $warga->jenis_kelamin,
            'dusun'          => $warga->dusun,
            'rt'             => $warga->rt,
            'rw'             => $warga->rw,
            'alamat'         => $warga->alamat,
            'no_hp'          => $warga->no_hp,
            'status_nikah'   => $warga->status_nikah,
            'pekerjaan'      => $warga->pekerjaan,
            'catatan'        => $warga->catatan,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nik'           => ['required', 'string', 'digits:16', 'unique:warga,nik'],
            'nama'          => ['required', 'string', 'max:100'],
            'tanggal_lahir' => ['required', 'date'],
            'jenis_kelamin' => ['required', 'in:Laki-laki,Perempuan'],
            'dusun'         => ['required', 'string'],
            'rt'            => ['required', 'string', 'digits:3'],
            'rw'            => ['required', 'string', 'digits:3'],
            'alamat'        => ['required', 'string'],
            'no_hp'         => ['nullable', 'string', 'max:15'],
            'status_nikah'  => ['nullable', 'in:Menikah,Tidak Menikah'],
            'pekerjaan'     => ['nullable', 'string', 'max:100'],
            'catatan'       => ['nullable', 'string'],
        ]);

        Warga::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Warga berhasil ditambahkan!'
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $warga = Warga::findOrFail($id);

        $validated = $request->validate([
            'nik'           => ['required', 'string', 'digits:16', "unique:warga,nik,{$id}"],
            'nama'          => ['required', 'string', 'max:100'],
            'tanggal_lahir' => ['required', 'date'],
            'jenis_kelamin' => ['required', 'in:Laki-laki,Perempuan'],
            'dusun'         => ['required', 'string'],
            'rt'            => ['required', 'string', 'digits:3'],
            'rw'            => ['required', 'string', 'digits:3'],
            'alamat'        => ['required', 'string'],
            'no_hp'         => ['nullable', 'string', 'max:15'],
            'status_nikah'  => ['nullable', 'in:Menikah,Tidak Menikah'],
            'pekerjaan'     => ['nullable', 'string', 'max:100'],
            'catatan'       => ['nullable', 'string'],
        ]);

        $warga->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data warga diperbarui!'
        ], 200);
    }


    public function destroy($id)
    {
        $warga = Warga::findOrFail($id);
        $warga->delete();
        return response()->json(['success' => true, 'message' => 'Warga dihapus!']);
    }
}
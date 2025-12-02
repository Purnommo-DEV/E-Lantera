<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        $roles = Role::all();
        return view('page.user.index', compact('roles'));
    }

    public function data()
    {
        $users = User::with('roles')->get()->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'roles' => $u->roles->pluck('name')->toArray() // ← HARUS ARRAY!
            ];
        });

        return response()->json(['data' => $users]); // DataTables butuh format { data: [...] }
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'roles' => 'required|array'
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password)
        ]);
        $user->syncRoles($request->roles);

        return response()->json(['success' => true, 'message' => 'User dibuat!']);
    }

    public function show($id)
    {
        $user = User::with('roles')->findOrFail($id);
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles->pluck('name')->toArray() // ← HARUS ARRAY!
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $request->validate([
            'name' => 'required',
            'email' => "required|email|unique:users,email,$id",
            'roles' => 'required|array'
        ]);

        $user->update($request->only(['name', 'email']));
        if ($request->filled('password')) {
            $user->update(['password' => bcrypt($request->password)]);
        }
        $user->syncRoles($request->roles);

        return response()->json(['success' => true, 'message' => 'User diperbarui!']);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        if ($user->hasRole('SuperAdmin') && User::role('SuperAdmin')->count() === 1) {
            return response()->json(['success' => false, 'message' => 'Tidak bisa hapus SuperAdmin terakhir!'], 400);
        }
        $user->delete();
        return response()->json(['success' => true, 'message' => 'User dihapus!']);
    }
}
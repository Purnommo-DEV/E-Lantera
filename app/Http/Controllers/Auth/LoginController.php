<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{

    public function index(){
        return view('page.dashboard');
        // return view('admin.role');
    }

    public function logout(Request $request)
    {
        Auth::logout(); // Hapus session user login

        $request->session()->invalidate(); // Hapus semua data session
        $request->session()->regenerateToken(); // Regenerasi CSRF token baru

        return redirect()->route('login')->with('status', 'Anda telah keluar dari sistem.');
    }

}

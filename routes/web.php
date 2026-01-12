<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\WargaController;
use App\Http\Controllers\PemeriksaanDewasaLansiaController;
use App\Http\Controllers\PemeriksaanLansiaController;
use App\Http\Controllers\RekapController;
use App\Http\Controllers\RegisterDewasaLansiaController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Halaman login (guest only)
require __DIR__.'/auth.php';

// ===============================================
// SEMUA ROUTE YANG HANYA BISA DIAKSES SETELAH LOGIN
// ===============================================
Route::middleware(['auth'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');

    // Logout

    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // ===========================================
    // 1. DATA WARGA
    // ===========================================
    Route::prefix('warga')->name('warga.')->group(function () {
        Route::get('/', [WargaController::class, 'index'])->name('index');
        Route::get('/data', [WargaController::class, 'data'])->name('data');
        Route::post('/', [WargaController::class, 'store'])->name('store');
        Route::get('/{warga}', [WargaController::class, 'show'])->name('show');
        Route::put('/{warga}', [WargaController::class, 'update'])->name('update');
        Route::delete('/{warga}', [WargaController::class, 'destroy'])->name('destroy');
    });

    // ===========================================
    // 2. PEMERIKSAAN DEWASA
    // ===========================================
    Route::prefix('dewasa')->name('dewasa.')->group(function () {

        Route::get('/', [PemeriksaanDewasaLansiaController::class, 'index'])->name('index');
        Route::get('/data', [PemeriksaanDewasaLansiaController::class, 'data'])->name('data');
        Route::get('/{warga}/form', [PemeriksaanDewasaLansiaController::class, 'form'])->name('form');
        Route::post('/{warga}', [PemeriksaanDewasaLansiaController::class, 'storeAjax'])->name('store.ajax');
        Route::get('/{warga}/edit/{periksa}', [PemeriksaanDewasaLansiaController::class, 'editAjax'])->name('edit.ajax');
        Route::put('/{warga}/{periksa}', [PemeriksaanDewasaLansiaController::class, 'updateAjax'])->name('update.ajax');
        Route::delete('/{periksa}', [PemeriksaanDewasaLansiaController::class, 'destroyAjax'])->name('destroy.ajax');
        Route::get('/{warga}/riwayat', [PemeriksaanDewasaLansiaController::class, 'riwayat'])->name('riwayat');
        Route::get('/{warga}/export-excel', [PemeriksaanDewasaLansiaController::class, 'exportKartuExcelSatuan'])->name('exportSatuan');
        Route::get('/export-excel-all', [PemeriksaanDewasaLansiaController::class, 'exportKartuExcelSemua'])->name('exportSemua');
        Route::get('/export-selected', [PemeriksaanDewasaLansiaController::class, 'exportSelected'])->name('dewasa.exportSelected');
    });

    // ===========================================
    // 3. PEMERIKSAAN LANSIA (AKS + SKILAS)
    // ===========================================
    Route::prefix('lansia')->name('lansia.')->group(function () {
        Route::get('/', [PemeriksaanLansiaController::class, 'index'])->name('index');
        Route::get('/data', [PemeriksaanLansiaController::class, 'data'])->name('data');
        Route::get('/form/{warga}', [PemeriksaanLansiaController::class, 'form'])->name('form');
        Route::post('/', [PemeriksaanLansiaController::class, 'store'])->name('store');
        Route::get('/{warga}/riwayat', [PemeriksaanLansiaController::class, 'riwayat'])->name('riwayat');
        Route::get('/{warga}/edit/{lansia}', [PemeriksaanLansiaController::class, 'edit'])->name('edit');
        Route::put('/{lansia}', [PemeriksaanLansiaController::class, 'update'])->name('update');
        Route::delete('/{lansia}', [PemeriksaanLansiaController::class, 'destroy'])->name('destroy');
        Route::get('/{warga}/export-excel', [PemeriksaanLansiaController::class, 'exportLansiaExcelSatuan'])->name('export');
        Route::get('/export-excel-all', [PemeriksaanLansiaController::class, 'exportLansiaExcelSemua'])->name('exportSemua');
        Route::get('/export-selected', [PemeriksaanLansiaController::class, 'exportSelected'])->name('lansia.exportSelected');
    });

    // ===========================================
    // 4. REKAP BULANAN
    // ===========================================
    Route::prefix('rekap')->name('rekap.')->group(function () {
        Route::get('/', [RekapController::class, 'index'])->name('index');
        Route::get('/bulanan', [RekapController::class, 'bulanan'])->name('bulanan');
        Route::get('/bulanan/data', [RekapController::class, 'dataBulanan'])->name('bulanan.data');
        Route::get('/bulanan/detail/{tahun}/{bulan}', [RekapController::class, 'detailBulanan'])->name('bulanan.detail');
        Route::get('/bulanan/export', [RekapController::class, 'exportExcel'])->name('bulanan.export');
        Route::get('/tahunan/kemenkes', [RekapController::class, 'exportKemenkesTahunan'])->name('tahunan.kemenkes');
    });

    // ===========================================
    // 5. MANAJEMEN USER — TETAP ADA & AMAN 100%
    // ===========================================
    Route::prefix('user')->name('user.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/data', [UserController::class, 'data'])->name('data');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::get('/{id}', [UserController::class, 'show'])->name('show');
        Route::put('/{id}', [UserController::class, 'update'])->name('update');
        Route::delete('/{id}', [UserController::class, 'destroy'])->name('destroy');
    });

    // ===========================================
    // 6. MANAJEMEN ROLE — TETAP ADA & AMAN 100%
    // ===========================================
    Route::prefix('role')->name('role.')->group(function () {
        Route::get('/', [RoleController::class, 'index'])->name('index');
        Route::get('/data', [RoleController::class, 'data'])->name('data');
        Route::post('/', [RoleController::class, 'store'])->name('store');
        Route::get('/{id}', [RoleController::class, 'show'])->name('show');
        Route::put('/{id}', [RoleController::class, 'update'])->name('update');
        Route::delete('/{id}', [RoleController::class, 'destroy'])->name('destroy');
    });

    // ===========================================
    // REGISTER DEWASA & LANSIA
    // ===========================================
    Route::prefix('register/dewasa-lansia')
        ->name('register.dewasa-lansia.')
        ->group(function () {

            Route::get('/', [RegisterDewasaLansiaController::class, 'index'])
                ->name('index');

            Route::get('/export/excel', [RegisterDewasaLansiaController::class, 'exportExcel'])
                ->name('export.excel');
        });

});
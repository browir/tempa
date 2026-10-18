<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/masuk', [LoginController::class, 'create'])->name('login');
    Route::post('/masuk', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/keluar', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/pengaturan', [SettingsController::class, 'edit'])->name('settings');
});

/*
 * Migrasi + akun pemilik di produksi, dipicu manual setelah deploy (cadangan bila
 * `php artisan migrate --env=supabase` dari laptop tidak bisa dipakai):
 *   https://<domain>/_ops/migrate/<token>
 * Token = HMAC-SHA256("ops-migrate", APP_KEY), jadi hanya pemegang APP_KEY yang bisa memanggilnya.
 * (Build Vercel tidak menerima env dashboard, jadi migrasi saat build tidak bisa diandalkan.)
 */
Route::get('/_ops/migrate/{token}', function (string $token) {
    abort_unless(hash_equals(hash_hmac('sha256', 'ops-migrate', (string) config('app.key')), $token), 404);

    $output = '';

    try {
        Artisan::call('migrate', ['--force' => true]);
        $output .= Artisan::output();

        Artisan::call('db:seed', ['--force' => true]);
        $output .= Artisan::output();
    } catch (Throwable $e) {
        return response($output."\nGAGAL: ".get_class($e).': '.$e->getMessage(), 500, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    return response(trim($output) ?: 'Tidak ada perubahan.', 200, ['Content-Type' => 'text/plain; charset=utf-8']);
});

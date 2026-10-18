<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\WorkoutController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/masuk', [LoginController::class, 'create'])->name('login');
    Route::post('/masuk', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/keluar', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');

    // Sesi latihan. Aksi tulis dipanggil outbox (JSON, idempoten); {workout} = client_id.
    // Hanya UUID: kolom client_id bertipe uuid di Postgres, teks lain akan galat 500 alih-alih 404.
    $uuid = '[\da-fA-F]{8}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{12}';
    Route::pattern('workout', $uuid);
    Route::pattern('clientId', $uuid);

    Route::get('/latihan', [WorkoutController::class, 'index'])->name('workouts.index');
    Route::post('/latihan', [WorkoutController::class, 'store'])->name('workouts.store');
    Route::put('/latihan/{workout}', [WorkoutController::class, 'update'])->name('workouts.update');
    Route::post('/latihan/{workout}/selesai', [WorkoutController::class, 'finish'])->name('workouts.finish');
    Route::delete('/latihan/{workout}', [WorkoutController::class, 'destroy'])->name('workouts.destroy')->withTrashed();
    Route::post('/latihan/{workout}/gerakan', [WorkoutController::class, 'storeExercise'])->name('workout-exercises.store');
    Route::put('/latihan/{workout}/gerakan/{clientId}', [WorkoutController::class, 'updateExercise'])->name('workout-exercises.update');
    Route::delete('/latihan/{workout}/gerakan/{clientId}', [WorkoutController::class, 'destroyExercise'])->name('workout-exercises.destroy');
    Route::put('/latihan/{workout}/set/{clientId}', [WorkoutController::class, 'upsertSet'])->name('sets.upsert');
    Route::delete('/latihan/{workout}/set/{clientId}', [WorkoutController::class, 'destroySet'])->name('sets.destroy');

    Route::get('/gerakan', [ExerciseController::class, 'index'])->name('exercises.index');
    Route::post('/gerakan', [ExerciseController::class, 'store'])->name('exercises.store');
    Route::put('/gerakan/{exercise}', [ExerciseController::class, 'update'])->name('exercises.update');
    Route::post('/gerakan/{exercise}/arsipkan', [ExerciseController::class, 'archive'])->name('exercises.archive');
    Route::post('/gerakan/{exercise}/pulihkan', [ExerciseController::class, 'unarchive'])->name('exercises.unarchive');

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
        Artisan::call('db:ensure-schema');
        $output .= Artisan::output();

        Artisan::call('migrate', ['--force' => true]);
        $output .= Artisan::output();

        Artisan::call('db:seed', ['--force' => true]);
        $output .= Artisan::output();
    } catch (Throwable $e) {
        return response($output."\nGAGAL: ".get_class($e).': '.$e->getMessage(), 500, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    return response(trim($output) ?: 'Tidak ada perubahan.', 200, ['Content-Type' => 'text/plain; charset=utf-8']);
});

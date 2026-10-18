<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sesi latihan, gerakan di dalam sesi, dan set.
 * Semua dibuat di klien lebih dulu (offline-first) dan dikirim lewat outbox, jadi tiap baris
 * punya client_id unik: kirim ulang selalu aman, dan baris saling merujuk lewat client_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_id')->unique();
            $table->unsignedBigInteger('routine_id')->nullable(); // FK ditambah saat tabel routines dibuat
            $table->string('name', 80)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable(); // null = sesi aktif (maks. satu per pengguna)
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'ended_at']);
            $table->index(['user_id', 'started_at']);
        });

        Schema::create('workout_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_id')->constrained()->cascadeOnDelete();
            // Gerakan tidak pernah dihapus (hanya diarsipkan), jadi tanpa cascade.
            $table->foreignId('exercise_id')->constrained();
            $table->uuid('client_id')->unique();
            $table->unsignedInteger('sort')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['exercise_id', 'workout_id']);
        });

        Schema::create('sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_exercise_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_id')->unique();
            $table->unsignedInteger('sort')->default(0);
            $table->string('kind', 10)->default('normal'); // warmup | normal | drop | failure
            // Satuan terkecil, integer: gram, detik, meter.
            $table->unsignedInteger('weight_g')->nullable();
            $table->unsignedSmallInteger('reps')->nullable();
            $table->unsignedInteger('duration_s')->nullable();
            $table->unsignedInteger('distance_m')->nullable();
            $table->unsignedTinyInteger('rpe')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        if (DB::getDriverName() === 'pgsql') {
            foreach (['workouts', 'workout_exercises', 'sets'] as $table) {
                DB::statement("ALTER TABLE \"{$table}\" ENABLE ROW LEVEL SECURITY");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sets');
        Schema::dropIfExists('workout_exercises');
        Schema::dropIfExists('workouts');
    }
};

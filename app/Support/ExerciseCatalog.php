<?php

namespace App\Support;

use App\Models\User;

/**
 * Daftar baku gerakan: kelompok otot, alat, jenis pencatatan, dan gerakan bawaan.
 * Sumber tunggal untuk validasi (backend) dan label (frontend lewat props bersama `catalog`).
 */
class ExerciseCatalog
{
    /** Urutan di sini = urutan tampil. */
    public const MUSCLE_GROUPS = [
        'chest' => 'Dada',
        'back' => 'Punggung',
        'shoulders' => 'Bahu',
        'biceps' => 'Bisep',
        'triceps' => 'Trisep',
        'forearms' => 'Lengan bawah',
        'core' => 'Perut',
        'quads' => 'Paha depan',
        'hamstrings' => 'Paha belakang',
        'glutes' => 'Bokong',
        'calves' => 'Betis',
        'full_body' => 'Seluruh tubuh',
        'cardio' => 'Kardio',
    ];

    public const EQUIPMENT = [
        'barbell' => 'Barbel',
        'dumbbell' => 'Dumbel',
        'machine' => 'Mesin',
        'cable' => 'Kabel',
        'kettlebell' => 'Kettlebell',
        'band' => 'Resistance band',
        'bodyweight' => 'Beban tubuh',
        'bike' => 'Sepeda',
        'none' => 'Tanpa alat',
        'other' => 'Lainnya',
    ];

    /**
     * Jenis menentukan isian set:
     * - weight_reps: berat × reps (volume dihitung)
     * - bodyweight:  reps, beban tambahan opsional (mis. pull up berbeban)
     * - time:        durasi (plank, wall sit)
     * - cardio:      jarak + durasi; kecepatan/tempo dihitung, tidak disimpan
     */
    public const KINDS = [
        // label: judul pilihan, hint: keterangan di form, short: keterangan ringkas di daftar.
        'weight_reps' => ['label' => 'Beban', 'hint' => 'Berat × reps', 'short' => 'Berat × reps'],
        'bodyweight' => ['label' => 'Beban tubuh', 'hint' => 'Reps, beban tambahan opsional', 'short' => 'Reps'],
        'time' => ['label' => 'Waktu', 'hint' => 'Durasi', 'short' => 'Durasi'],
        'cardio' => ['label' => 'Kardio', 'hint' => 'Jarak + durasi', 'short' => 'Jarak + durasi'],
    ];

    /** Untuk props bersama: daftar berurutan [{value, label}] agar urutan terjaga di JS. */
    public static function forFrontend(): array
    {
        $list = fn (array $items) => collect($items)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values();

        return [
            'muscleGroups' => $list(self::MUSCLE_GROUPS),
            'equipment' => $list(self::EQUIPMENT),
            'kinds' => collect(self::KINDS)->map(fn ($k, $value) => ['value' => $value] + $k)->values(),
        ];
    }

    /**
     * Gerakan bawaan: gym, rumahan (beban tubuh), dan kardio — terutama sepeda.
     * [nama, kelompok otot, alat, jenis]
     */
    public static function defaults(): array
    {
        return [
            // Gym
            ['Bench Press', 'chest', 'barbell', 'weight_reps'],
            ['Incline Dumbbell Press', 'chest', 'dumbbell', 'weight_reps'],
            ['Dumbbell Fly', 'chest', 'dumbbell', 'weight_reps'],
            ['Chest Press Machine', 'chest', 'machine', 'weight_reps'],
            ['Cable Crossover', 'chest', 'cable', 'weight_reps'],
            ['Deadlift', 'back', 'barbell', 'weight_reps'],
            ['Lat Pulldown', 'back', 'cable', 'weight_reps'],
            ['Seated Cable Row', 'back', 'cable', 'weight_reps'],
            ['Barbell Row', 'back', 'barbell', 'weight_reps'],
            ['Dumbbell Row', 'back', 'dumbbell', 'weight_reps'],
            ['Overhead Press', 'shoulders', 'barbell', 'weight_reps'],
            ['Dumbbell Shoulder Press', 'shoulders', 'dumbbell', 'weight_reps'],
            ['Lateral Raise', 'shoulders', 'dumbbell', 'weight_reps'],
            ['Face Pull', 'shoulders', 'cable', 'weight_reps'],
            ['Barbell Curl', 'biceps', 'barbell', 'weight_reps'],
            ['Dumbbell Curl', 'biceps', 'dumbbell', 'weight_reps'],
            ['Hammer Curl', 'biceps', 'dumbbell', 'weight_reps'],
            ['Tricep Pushdown', 'triceps', 'cable', 'weight_reps'],
            ['Overhead Tricep Extension', 'triceps', 'dumbbell', 'weight_reps'],
            ['Skull Crusher', 'triceps', 'barbell', 'weight_reps'],
            ['Wrist Curl', 'forearms', 'dumbbell', 'weight_reps'],
            ['Cable Crunch', 'core', 'cable', 'weight_reps'],
            ['Back Squat', 'quads', 'barbell', 'weight_reps'],
            ['Front Squat', 'quads', 'barbell', 'weight_reps'],
            ['Leg Press', 'quads', 'machine', 'weight_reps'],
            ['Leg Extension', 'quads', 'machine', 'weight_reps'],
            ['Bulgarian Split Squat', 'quads', 'dumbbell', 'weight_reps'],
            ['Goblet Squat', 'quads', 'kettlebell', 'weight_reps'],
            ['Romanian Deadlift', 'hamstrings', 'barbell', 'weight_reps'],
            ['Leg Curl', 'hamstrings', 'machine', 'weight_reps'],
            ['Hip Thrust', 'glutes', 'barbell', 'weight_reps'],
            ['Kettlebell Swing', 'glutes', 'kettlebell', 'weight_reps'],
            ['Standing Calf Raise', 'calves', 'machine', 'weight_reps'],

            // Rumahan / beban tubuh
            ['Push Up', 'chest', 'bodyweight', 'bodyweight'],
            ['Diamond Push Up', 'triceps', 'bodyweight', 'bodyweight'],
            ['Pike Push Up', 'shoulders', 'bodyweight', 'bodyweight'],
            ['Pull Up', 'back', 'bodyweight', 'bodyweight'],
            ['Chin Up', 'back', 'bodyweight', 'bodyweight'],
            ['Inverted Row', 'back', 'bodyweight', 'bodyweight'],
            ['Dip', 'triceps', 'bodyweight', 'bodyweight'],
            ['Bodyweight Squat', 'quads', 'bodyweight', 'bodyweight'],
            ['Lunge', 'quads', 'bodyweight', 'bodyweight'],
            ['Jump Squat', 'quads', 'bodyweight', 'bodyweight'],
            ['Glute Bridge', 'glutes', 'bodyweight', 'bodyweight'],
            ['Single-Leg Calf Raise', 'calves', 'bodyweight', 'bodyweight'],
            ['Sit Up', 'core', 'bodyweight', 'bodyweight'],
            ['Crunch', 'core', 'bodyweight', 'bodyweight'],
            ['Leg Raise', 'core', 'bodyweight', 'bodyweight'],
            ['Bicycle Crunch', 'core', 'bodyweight', 'bodyweight'],
            ['Russian Twist', 'core', 'bodyweight', 'bodyweight'],
            ['Burpee', 'full_body', 'bodyweight', 'bodyweight'],
            ['Mountain Climber', 'full_body', 'bodyweight', 'bodyweight'],
            ['Jumping Jack', 'full_body', 'bodyweight', 'bodyweight'],
            ['Plank', 'core', 'bodyweight', 'time'],
            ['Side Plank', 'core', 'bodyweight', 'time'],
            ['Hollow Hold', 'core', 'bodyweight', 'time'],
            ['Wall Sit', 'quads', 'bodyweight', 'time'],
            ['Dead Hang', 'forearms', 'bodyweight', 'time'],

            // Kardio
            ['Cycling', 'cardio', 'bike', 'cardio'],
            ['Indoor Cycling', 'cardio', 'bike', 'cardio'],
            ['Running', 'cardio', 'none', 'cardio'],
            ['Treadmill Running', 'cardio', 'machine', 'cardio'],
            ['Walking', 'cardio', 'none', 'cardio'],
            ['Hiking', 'cardio', 'none', 'cardio'],
            ['Swimming', 'cardio', 'none', 'cardio'],
            ['Rowing Machine', 'cardio', 'machine', 'cardio'],
            ['Elliptical', 'cardio', 'machine', 'cardio'],
            ['Jump Rope', 'cardio', 'other', 'time'],
        ];
    }

    /** Isi gerakan bawaan bila pengguna belum punya gerakan sama sekali (idempoten). */
    public static function seedFor(User $user): int
    {
        if ($user->exercises()->exists()) {
            return 0;
        }

        $now = now();
        $rows = collect(self::defaults())->values()->map(fn ($e, $i) => [
            'user_id' => $user->id,
            'name' => $e[0],
            'muscle_group' => $e[1],
            'equipment' => $e[2],
            'kind' => $e[3],
            'sort' => $i,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $user->exercises()->insert($rows->all());

        return $rows->count();
    }
}

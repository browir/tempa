<?php

namespace App\Support;

use App\Models\Exercise;
use App\Models\User;
use Illuminate\Support\Collection;

/** Data gerakan ringkas untuk halaman sesi & riwayat (nama, jenis, otot, alat). */
class ExercisePayload
{
    public static function forUser(User $user): Collection
    {
        return $user->exercises()->orderBy('sort')->orderBy('id')
            ->get(['id', 'name', 'muscle_group', 'equipment', 'kind', 'archived_at'])
            ->map(fn (Exercise $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'muscle_group' => $e->muscle_group,
                'equipment' => $e->equipment,
                'kind' => $e->kind,
                'archived' => $e->archived_at !== null,
            ]);
    }
}

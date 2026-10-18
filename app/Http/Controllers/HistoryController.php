<?php

namespace App\Http\Controllers;

use App\Models\Workout;
use App\Support\ExercisePayload;
use App\Support\WorkoutPayload;
use App\Support\WorkoutStats;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Riwayat sesi yang sudah selesai (baca saja; ubah/hapus menyusul di Tahap 1 item 5). */
class HistoryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $workouts = $user->workouts()->finished()
            ->latest('started_at')
            ->limit(100)
            ->with('exercises.sets')
            ->get()
            ->map(function (Workout $w) {
                $done = $w->exercises->flatMap->sets->whereNotNull('done_at');

                return [
                    'client_id' => $w->client_id,
                    'name' => $w->name,
                    'started_at' => $w->started_at->toIso8601String(),
                    'duration_s' => $w->durationSeconds(),
                    'exercise_ids' => $w->exercises->pluck('exercise_id')->values(),
                    'sets_done' => $done->count(),
                    'volume_g' => WorkoutStats::volume($done),
                    'distance_m' => (int) $done->sum('distance_m'),
                ];
            });

        return Inertia::render('history/Index', [
            'workouts' => $workouts,
            'exercises' => ExercisePayload::forUser($user),
        ]);
    }

    public function show(Request $request, Workout $workout)
    {
        $this->ensureOwned($workout);
        if (! $workout->ended_at) {
            return redirect()->route('workouts.index');
        }

        return Inertia::render('history/Show', [
            'workout' => WorkoutPayload::full($workout) + ['duration_s' => $workout->durationSeconds()],
            'exercises' => ExercisePayload::forUser($request->user()),
        ]);
    }
}

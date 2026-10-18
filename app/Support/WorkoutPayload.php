<?php

namespace App\Support;

use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;

/** Bentuk data sesi untuk frontend — sama dengan bentuk sesi lokal di useWorkout.js. */
class WorkoutPayload
{
    public static function full(Workout $workout): array
    {
        $workout->loadMissing('exercises.sets');

        return [
            'client_id' => $workout->client_id,
            'name' => $workout->name,
            'notes' => $workout->notes,
            'started_at' => $workout->started_at->toIso8601String(),
            'ended_at' => $workout->ended_at?->toIso8601String(),
            'paused_at' => $workout->paused_at?->toIso8601String(),
            'paused_seconds' => $workout->paused_seconds,
            'exercises' => $workout->exercises->map(fn (WorkoutExercise $we) => [
                'client_id' => $we->client_id,
                'exercise_id' => $we->exercise_id,
                'sort' => $we->sort,
                'notes' => $we->notes,
                'sets' => $we->sets->map(fn (WorkoutSet $s) => self::set($s))->values(),
            ])->values(),
        ];
    }

    public static function set(WorkoutSet $set): array
    {
        return [
            'client_id' => $set->client_id,
            'sort' => $set->sort,
            'kind' => $set->kind,
            'weight_g' => $set->weight_g,
            'reps' => $set->reps,
            'duration_s' => $set->duration_s,
            'distance_m' => $set->distance_m,
            'rpe' => $set->rpe,
            'done_at' => $set->done_at?->toIso8601String(),
        ];
    }
}

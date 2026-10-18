import { setCounts, volume } from '@/lib/sets';

/** Estimasi 1RM Epley (cermin WorkoutStats::estimatedOneRepMax), gram. Hanya reps 1–12. */
export function oneRepMax(weightG, reps) {
    if (!weightG || !reps || reps < 1 || reps > 12) return null;
    return reps === 1 ? weightG : Math.round(weightG * (1 + reps / 30));
}

/** Durasi bersih sesi (detik): selesai/jeda/sekarang − mulai − total jeda. */
export function workoutSeconds(workout, nowMs = Date.now()) {
    if (!workout) return 0;
    const end = workout.ended_at ?? workout.paused_at;
    const endMs = end ? new Date(end).getTime() : nowMs;
    return Math.max(0, Math.floor((endMs - new Date(workout.started_at).getTime()) / 1000) - (workout.paused_seconds ?? 0));
}

/** Set terbaik per jenis gerakan. */
function bestSet(kind, sets) {
    const pick = (score) => sets.reduce((best, s) => (best === null || score(s) > score(best) ? s : best), null);
    switch (kind) {
        case 'bodyweight':
            return pick((s) => (s.reps ?? 0) * 1e6 + (s.weight_g ?? 0));
        case 'time':
            return pick((s) => s.duration_s ?? 0);
        case 'cardio':
            return pick((s) => (s.distance_m ?? 0) * 1e6 + (s.duration_s ?? 0));
        default: {
            const counted = sets.filter(setCounts);
            if (!counted.length) return null;
            return counted.reduce((best, s) => {
                const a = oneRepMax(s.weight_g, s.reps) ?? s.weight_g;
                const b = oneRepMax(best.weight_g, best.reps) ?? best.weight_g;
                return a > b || (a === b && s.weight_g > best.weight_g) ? s : best;
            });
        }
    }
}

/** Ringkasan sesi untuk layar "selesai" dan detail riwayat. */
export function summarize(workout, exercisesById) {
    const exercises = (workout?.exercises ?? []).map((entry) => {
        const exercise = exercisesById[entry.exercise_id] ?? { id: entry.exercise_id, name: 'Gerakan', kind: 'weight_reps' };
        const done = entry.sets.filter((s) => s.done_at);
        const best = bestSet(exercise.kind, done);
        return {
            entry,
            exercise,
            done,
            best,
            bestOneRepMax: exercise.kind === 'weight_reps' && best ? oneRepMax(best.weight_g, best.reps) : null,
            volume: volume(done),
            distance: done.reduce((sum, s) => sum + (s.distance_m ?? 0), 0),
            duration: done.reduce((sum, s) => sum + (s.duration_s ?? 0), 0),
        };
    });

    const done = exercises.flatMap((e) => e.done);
    return {
        exercises,
        seconds: workoutSeconds(workout),
        sets: done.length,
        volume: volume(done),
        distance: exercises.filter((e) => e.exercise.kind === 'cardio').reduce((sum, e) => sum + e.distance, 0),
        cardioSeconds: exercises.filter((e) => e.exercise.kind === 'cardio').reduce((sum, e) => sum + e.duration, 0),
    };
}

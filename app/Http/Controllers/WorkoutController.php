<?php

namespace App\Http\Controllers;

use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use App\Support\ClientTime;
use App\Support\ExercisePayload;
use App\Support\WorkoutPayload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Sesi latihan. Halaman /latihan menampilkan sesi dari penyimpanan lokal (offline-first);
 * aksi tulis dipanggil oleh outbox di klien dan membalas JSON. Semua aksi tulis idempoten.
 */
class WorkoutController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $active = $user->workouts()->active()->latest('started_at')->first();

        // Sesi selesai terakhir per gerakan → kolom "Sebelumnya" + isi otomatis.
        $latest = WorkoutExercise::query()
            ->join('workouts', 'workouts.id', '=', 'workout_exercises.workout_id')
            ->where('workouts.user_id', $user->id)
            ->whereNotNull('workouts.ended_at')
            ->whereNull('workouts.deleted_at')
            // Hanya kemunculan yang punya set selesai; gerakan yang dilewati tidak menghapus acuan lama.
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('sets')
                ->whereColumn('sets.workout_exercise_id', 'workout_exercises.id')
                ->whereNotNull('sets.done_at')
                ->whereNull('sets.deleted_at'))
            ->groupBy('workout_exercises.exercise_id')
            ->selectRaw('workout_exercises.exercise_id, max(workout_exercises.id) as latest_id')
            ->pluck('latest_id', 'exercise_id');

        $history = WorkoutExercise::query()
            ->whereIn('id', $latest->values())
            ->with(['workout:id,started_at', 'sets' => fn ($q) => $q->whereNotNull('done_at')])
            ->get()
            ->filter(fn ($we) => $we->sets->isNotEmpty())
            ->mapWithKeys(fn ($we) => [$we->exercise_id => [
                'date' => $we->workout->started_at->toDateString(),
                'sets' => $we->sets->map(fn ($s) => [
                    'kind' => $s->kind,
                    'weight_g' => $s->weight_g,
                    'reps' => $s->reps,
                    'duration_s' => $s->duration_s,
                    'distance_m' => $s->distance_m,
                ])->values(),
            ]]);

        return Inertia::render('workouts/Index', [
            'active' => $active ? WorkoutPayload::full($active) : null,
            'exercises' => ExercisePayload::forUser($user),
            'history' => (object) $history->all(),
            // Gerakan yang baru dipakai (id terbesar = paling baru), untuk bagian atas pemilih gerakan.
            'recent' => $latest->sortDesc()->keys()->take(12)->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id' => ['required', 'uuid'],
            'started_at' => ['required', 'date'],
            'name' => ['nullable', 'string', 'max:80'],
        ], [
            'client_id.*' => 'ID sesi tidak valid.',
            'started_at.*' => 'Waktu mulai tidak valid.',
            'name.max' => 'Nama sesi maksimal 80 karakter.',
        ]);
        $user = $request->user();

        $existing = Workout::withTrashed()->where('client_id', $data['client_id'])->first();
        if ($existing) {
            abort_unless($existing->user_id === $user->id, 404);

            return $this->ok();
        }

        // Satu sesi aktif per pengguna. UI menawarkan "Lanjutkan" lebih dulu, tapi sesi baru bisa tetap
        // datang dari antrean offline (mis. HP lain / data lokal terhapus). Menolaknya akan membuat antrean
        // macet, jadi sesi lama yang menggantung ditutup: selesai pada set terakhirnya, atau dibuang bila kosong.
        $user->workouts()->active()->get()->each(fn (Workout $old) => $this->closeAbandoned($old));

        $user->workouts()->create([
            'client_id' => $data['client_id'],
            'name' => $data['name'] ?? null,
            'started_at' => ClientTime::parse($data['started_at']),
        ]);

        return $this->ok(201);
    }

    public function update(Request $request, Workout $workout)
    {
        $this->ensureOwned($workout);
        $data = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:80'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            // Jeda: nilai mutlak dari HP (sumber kebenaran sesi aktif), jadi kirim ulang tetap aman.
            'paused_at' => ['sometimes', 'nullable', 'date'],
            'paused_seconds' => ['sometimes', 'integer', 'min:0', 'max:604800'],
        ], [
            'name.max' => 'Nama sesi maksimal 80 karakter.',
            'notes.max' => 'Catatan maksimal 2000 karakter.',
            'paused_at.*' => 'Waktu jeda tidak valid.',
            'paused_seconds.*' => 'Lama jeda tidak valid.',
        ]);
        if (array_key_exists('paused_at', $data)) {
            $data['paused_at'] = ClientTime::parse($data['paused_at']);
        }
        $workout->update($data);

        return $this->ok();
    }

    public function finish(Request $request, Workout $workout)
    {
        $this->ensureOwned($workout);
        $data = $request->validate([
            'ended_at' => ['required', 'date'],
            'paused_seconds' => ['nullable', 'integer', 'min:0', 'max:604800'],
        ], ['ended_at.*' => 'Waktu selesai tidak valid.', 'paused_seconds.*' => 'Lama jeda tidak valid.']);

        // Idempoten: kirim ulang tidak menggeser waktu selesai.
        if (! $workout->ended_at) {
            $endedAt = ClientTime::parse($data['ended_at']);
            $workout->update([
                'ended_at' => $endedAt->lt($workout->started_at) ? $workout->started_at : $endedAt,
                'paused_at' => null,
                'paused_seconds' => $data['paused_seconds'] ?? $workout->paused_seconds,
            ]);
        }

        return $this->ok();
    }

    /** Buang sesi (soft delete, bisa dipulihkan dari riwayat nanti). */
    public function destroy(Workout $workout)
    {
        $this->ensureOwned($workout);
        if (! $workout->trashed()) {
            $workout->delete();
        }

        return $this->ok();
    }

    public function storeExercise(Request $request, Workout $workout)
    {
        $this->ensureOwned($workout);
        $userId = $request->user()->id;
        $data = $request->validate([
            'client_id' => ['required', 'uuid'],
            'exercise_id' => ['required', 'integer', Rule::exists('exercises', 'id')->where('user_id', $userId)],
            'sort' => ['nullable', 'integer', 'min:0'],
        ], [
            'client_id.*' => 'ID gerakan sesi tidak valid.',
            'exercise_id.*' => 'Gerakan tidak ditemukan.',
        ]);

        $existing = WorkoutExercise::where('client_id', $data['client_id'])->first();
        if ($existing) {
            abort_unless($existing->workout_id === $workout->id, 404);

            return $this->ok();
        }

        $workout->exercises()->create($data + ['sort' => 0]);

        return $this->ok(201);
    }

    public function updateExercise(Request $request, Workout $workout, string $clientId)
    {
        $this->ensureOwned($workout);
        $we = $workout->exercises()->where('client_id', $clientId)->firstOrFail();
        $we->update($request->validate([
            'sort' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], ['notes.max' => 'Catatan maksimal 500 karakter.']));

        return $this->ok();
    }

    public function destroyExercise(Workout $workout, string $clientId)
    {
        $this->ensureOwned($workout);
        // Sudah terhapus = tujuan tercapai (kirim ulang aman).
        $workout->exercises()->where('client_id', $clientId)->first()?->delete();

        return $this->ok();
    }

    /** Buat atau perbarui satu set (PUT idempoten; set yang terhapus dipulihkan). */
    public function upsertSet(Request $request, Workout $workout, string $clientId)
    {
        $this->ensureOwned($workout);
        abort_unless(Str::isUuid($clientId), 404);

        $data = $request->validate([
            'workout_exercise' => ['required', 'uuid'],
            'sort' => ['required', 'integer', 'min:0'],
            'kind' => ['required', Rule::in(WorkoutSet::KINDS)],
            'weight_g' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'reps' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'duration_s' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'distance_m' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'rpe' => ['nullable', 'integer', 'min:1', 'max:10'],
            'done_at' => ['nullable', 'date'],
        ], [
            'kind.*' => 'Jenis set tidak dikenal.',
            'weight_g.*' => 'Berat harus antara 0 dan 1000 kg.',
            'reps.*' => 'Reps harus antara 0 dan 1000.',
            'duration_s.*' => 'Durasi maksimal 24 jam.',
            'distance_m.*' => 'Jarak maksimal 1000 km.',
            'rpe.*' => 'RPE harus 1–10.',
            'done_at.*' => 'Waktu selesai set tidak valid.',
        ]);

        $we = $workout->exercises()->where('client_id', $data['workout_exercise'])->first();
        if (! $we) {
            throw ValidationException::withMessages(['workout_exercise' => 'Gerakan untuk set ini tidak ada di sesi.']);
        }

        DB::transaction(function () use ($data, $we, $workout, $clientId) {
            $set = WorkoutSet::withTrashed()->with('workoutExercise:id,workout_id')->where('client_id', $clientId)->first();
            if ($set) {
                abort_unless($set->workoutExercise?->workout_id === $workout->id, 404);
            } else {
                $set = new WorkoutSet(['client_id' => $clientId]);
            }

            $set->workout_exercise_id = $we->id;
            $set->fill(collect($data)->except('workout_exercise')->all());
            $set->done_at = ClientTime::parse($data['done_at'] ?? null);
            if ($set->trashed()) {
                $set->deleted_at = null;
            }
            $set->save();
        });

        return $this->ok();
    }

    public function destroySet(Workout $workout, string $clientId)
    {
        $this->ensureOwned($workout);
        $set = WorkoutSet::where('client_id', $clientId)
            ->whereHas('workoutExercise', fn ($q) => $q->where('workout_id', $workout->id))
            ->first();
        $set?->delete();

        return $this->ok();
    }

    private function closeAbandoned(Workout $workout): void
    {
        if (! $workout->sets()->exists()) {
            $workout->delete();

            return;
        }

        $lastDone = $workout->sets()->max('sets.done_at');
        $workout->update(['ended_at' => $lastDone ?? $workout->started_at]);
    }

    private function ok(int $status = 200)
    {
        return response()->json(['ok' => true], $status);
    }
}

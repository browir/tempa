<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Support\ExerciseCatalog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ExerciseController extends Controller
{
    /**
     * Semua gerakan dikirim sekaligus (puluhan baris, satu pengguna); pencarian & filter otot
     * dilakukan di klien agar instan dan tetap jalan saat offline dari salinan halaman terakhir.
     */
    public function index(Request $request)
    {
        $order = array_flip(array_keys(ExerciseCatalog::MUSCLE_GROUPS));

        $exercises = $request->user()->exercises()
            ->orderBy('sort')->orderBy('id')
            ->get(['id', 'name', 'muscle_group', 'equipment', 'kind', 'notes', 'archived_at'])
            ->sortBy(fn ($e) => $order[$e->muscle_group] ?? PHP_INT_MAX)
            ->values()
            ->map(fn (Exercise $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'muscle_group' => $e->muscle_group,
                'equipment' => $e->equipment,
                'kind' => $e->kind,
                'notes' => $e->notes,
                'archived' => $e->archived_at !== null,
            ]);

        return Inertia::render('exercises/Index', ['exercises' => $exercises]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['sort'] = (int) $request->user()->exercises()->max('sort') + 1;
        $request->user()->exercises()->create($data);

        Inertia::flash('toast', ['message' => 'Gerakan ditambahkan']);

        return back();
    }

    public function update(Request $request, Exercise $exercise)
    {
        $this->ensureOwned($exercise);
        $exercise->update($this->validated($request, $exercise));

        Inertia::flash('toast', ['message' => 'Gerakan diperbarui']);

        return back();
    }

    public function archive(Exercise $exercise)
    {
        $this->ensureOwned($exercise);
        $exercise->update(['archived_at' => now()]);

        Inertia::flash('toast', [
            'message' => "{$exercise->name} diarsipkan",
            'undo' => route('exercises.unarchive', $exercise),
        ]);

        return back();
    }

    public function unarchive(Exercise $exercise)
    {
        $this->ensureOwned($exercise);
        $exercise->update(['archived_at' => null]);

        Inertia::flash('toast', ['message' => "{$exercise->name} aktif lagi"]);

        return back();
    }

    private function validated(Request $request, ?Exercise $exercise = null): array
    {
        $userId = $request->user()->id;

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:80',
                // Unik per pengguna, tanpa peduli huruf besar/kecil (Postgres `=` peka huruf besar).
                function (string $attribute, mixed $value, Closure $fail) use ($userId, $exercise) {
                    $taken = Exercise::where('user_id', $userId)
                        ->whereRaw('lower(name) = ?', [mb_strtolower(trim((string) $value))])
                        ->when($exercise, fn ($q) => $q->whereKeyNot($exercise->id))
                        ->exists();
                    if ($taken) {
                        $fail('Gerakan dengan nama ini sudah ada.');
                    }
                },
            ],
            'muscle_group' => ['required', Rule::in(array_keys(ExerciseCatalog::MUSCLE_GROUPS))],
            'equipment' => ['required', Rule::in(array_keys(ExerciseCatalog::EQUIPMENT))],
            'kind' => ['required', Rule::in(array_keys(ExerciseCatalog::KINDS))],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'name.required' => 'Nama gerakan wajib diisi.',
            'name.max' => 'Nama gerakan maksimal 80 karakter.',
            'muscle_group.required' => 'Pilih kelompok otot.',
            'muscle_group.in' => 'Kelompok otot tidak dikenal.',
            'equipment.required' => 'Pilih alat.',
            'equipment.in' => 'Alat tidak dikenal.',
            'kind.required' => 'Pilih jenis pencatatan.',
            'kind.in' => 'Jenis pencatatan tidak dikenal.',
            'notes.max' => 'Catatan maksimal 500 karakter.',
        ]);

        $data['name'] = trim($data['name']);

        return $data;
    }
}

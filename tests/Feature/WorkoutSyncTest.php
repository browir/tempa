<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSet;
use App\Support\WorkoutStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkoutSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Exercise $bench;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->bench = $this->user->exercises()->create([
            'name' => 'Bench Press', 'muscle_group' => 'chest', 'equipment' => 'barbell', 'kind' => 'weight_reps',
        ]);
    }

    /** Mulai sesi + satu gerakan lewat endpoint, seperti yang dikirim outbox. */
    private function startWorkout(?User $user = null, array $overrides = []): array
    {
        $user ??= $this->user;
        $workout = Str::uuid()->toString();
        $this->actingAs($user)->postJson(route('workouts.store'), [
            'client_id' => $workout, 'started_at' => '2026-10-18T10:00:00Z', ...$overrides,
        ])->assertCreated();

        return [$workout];
    }

    private function addExercise(string $workout, ?Exercise $exercise = null): string
    {
        $we = Str::uuid()->toString();
        $this->postJson(route('workout-exercises.store', $workout), [
            'client_id' => $we, 'exercise_id' => ($exercise ?? $this->bench)->id, 'sort' => 0,
        ])->assertCreated();

        return $we;
    }

    private function putSet(string $workout, string $we, string $set, array $data = [])
    {
        return $this->putJson(route('sets.upsert', [$workout, $set]), [
            'workout_exercise' => $we, 'sort' => 0, 'kind' => 'normal', 'weight_g' => 60000, 'reps' => 8,
            'done_at' => '2026-10-18T10:05:00Z', ...$data,
        ]);
    }

    public function test_mulai_sesi_idempoten_dengan_client_id(): void
    {
        $client = Str::uuid()->toString();
        $payload = ['client_id' => $client, 'started_at' => '2026-10-18T10:00:00Z'];

        $this->actingAs($this->user)->postJson(route('workouts.store'), $payload)->assertCreated();
        $this->actingAs($this->user)->postJson(route('workouts.store'), $payload)->assertOk();

        $this->assertSame(1, Workout::count());
    }

    public function test_waktu_dari_klien_disimpan_dalam_zona_wita(): void
    {
        [$workout] = $this->startWorkout();

        $this->assertSame('2026-10-18 18:00:00', Workout::sole()->started_at->format('Y-m-d H:i:s'));
    }

    public function test_hanya_satu_sesi_aktif_sesi_lama_kosong_dibuang(): void
    {
        [$old] = $this->startWorkout();
        [$new] = $this->startWorkout(overrides: ['started_at' => '2026-10-18T11:00:00Z']);

        $this->assertSame(1, Workout::active()->count());
        $this->assertSame($new, Workout::active()->sole()->client_id);
        $this->assertTrue(Workout::withTrashed()->where('client_id', $old)->sole()->trashed());
    }

    public function test_hanya_satu_sesi_aktif_sesi_lama_berisi_ditutup_pada_set_terakhir(): void
    {
        [$old] = $this->startWorkout();
        $we = $this->addExercise($old);
        $this->putSet($old, $we, Str::uuid()->toString(), ['done_at' => '2026-10-18T10:20:00Z'])->assertOk();
        $this->putSet($old, $we, Str::uuid()->toString(), ['sort' => 1, 'done_at' => '2026-10-18T10:25:00Z'])->assertOk();

        $this->startWorkout(overrides: ['started_at' => '2026-10-18T12:00:00Z']);

        $closed = Workout::where('client_id', $old)->sole();
        $this->assertSame('2026-10-18 18:25:00', $closed->ended_at->format('Y-m-d H:i:s'));
        $this->assertSame(1, Workout::active()->count());
        $this->assertSame(2, $closed->sets()->count());
    }

    public function test_props_bersama_memberi_tahu_sesi_aktif_di_server(): void
    {
        $this->actingAs($this->user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('activeWorkout', null));

        [$workout] = $this->startWorkout();

        $this->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('activeWorkout.client_id', $workout));
    }

    public function test_setelah_selesai_boleh_mulai_sesi_baru(): void
    {
        [$workout] = $this->startWorkout();
        $this->postJson(route('workouts.finish', $workout), ['ended_at' => '2026-10-18T11:00:00Z'])->assertOk();

        $this->startWorkout();
        $this->assertSame(2, Workout::count());
        $this->assertSame(1, Workout::active()->count());
    }

    public function test_set_dibuat_lalu_diperbarui_lewat_client_id_yang_sama(): void
    {
        [$workout] = $this->startWorkout();
        $we = $this->addExercise($workout);
        $set = Str::uuid()->toString();

        $this->putSet($workout, $we, $set, ['done_at' => null])->assertOk();
        $this->putSet($workout, $we, $set, ['weight_g' => 62500, 'reps' => 6])->assertOk();
        $this->putSet($workout, $we, $set, ['weight_g' => 62500, 'reps' => 6])->assertOk(); // kirim ulang

        $row = WorkoutSet::sole();
        $this->assertSame(62500, $row->weight_g);
        $this->assertSame(6, $row->reps);
        $this->assertNotNull($row->done_at);
    }

    public function test_tambah_gerakan_idempoten_dan_hanya_gerakan_milik_sendiri(): void
    {
        [$workout] = $this->startWorkout();
        $we = Str::uuid()->toString();
        $payload = ['client_id' => $we, 'exercise_id' => $this->bench->id, 'sort' => 0];

        $this->postJson(route('workout-exercises.store', $workout), $payload)->assertCreated();
        $this->postJson(route('workout-exercises.store', $workout), $payload)->assertOk();
        $this->assertSame(1, Workout::sole()->exercises()->count());

        $foreign = User::factory()->create()->exercises()->create([
            'name' => 'Squat', 'muscle_group' => 'quads', 'equipment' => 'barbell', 'kind' => 'weight_reps',
        ]);
        $this->postJson(route('workout-exercises.store', $workout), [
            'client_id' => Str::uuid()->toString(), 'exercise_id' => $foreign->id,
        ])->assertJsonValidationErrors(['exercise_id' => 'Gerakan tidak ditemukan.']);
    }

    public function test_sesi_milik_orang_lain_dianggap_tidak_ada(): void
    {
        $other = User::factory()->create();
        [$workout] = $this->startWorkout($other);
        $we = $this->addExercise($workout, $other->exercises()->create([
            'name' => 'Row', 'muscle_group' => 'back', 'equipment' => 'barbell', 'kind' => 'weight_reps',
        ]));

        $this->actingAs($this->user);
        $this->putSet($workout, $we, Str::uuid()->toString())->assertNotFound();
        $this->postJson(route('workouts.finish', $workout), ['ended_at' => '2026-10-18T11:00:00Z'])->assertNotFound();
        $this->deleteJson(route('workouts.destroy', $workout))->assertNotFound();
        $this->postJson(route('workout-exercises.store', $workout), ['client_id' => Str::uuid()->toString(), 'exercise_id' => $this->bench->id])->assertNotFound();
        // client_id sesi orang lain juga tidak bisa "diklaim" ulang lewat mulai sesi.
        $this->postJson(route('workouts.store'), ['client_id' => $workout, 'started_at' => '2026-10-18T10:00:00Z'])->assertNotFound();

        $this->assertNull(Workout::sole()->ended_at);
        $this->assertSame(0, WorkoutSet::count());
    }

    public function test_hapus_set_lalu_urungkan_memulihkan_set(): void
    {
        [$workout] = $this->startWorkout();
        $we = $this->addExercise($workout);
        $set = Str::uuid()->toString();
        $this->putSet($workout, $we, $set)->assertOk();

        $this->deleteJson(route('sets.destroy', [$workout, $set]))->assertOk();
        $this->deleteJson(route('sets.destroy', [$workout, $set]))->assertOk(); // kirim ulang aman
        $this->assertSame(0, WorkoutSet::count());

        $this->putSet($workout, $we, $set)->assertOk();
        $this->assertSame(1, WorkoutSet::count());
        $this->assertSame(1, WorkoutSet::withTrashed()->count());
    }

    public function test_selesai_idempoten_dan_buang_sesi(): void
    {
        [$workout] = $this->startWorkout();

        $this->postJson(route('workouts.finish', $workout), ['ended_at' => '2026-10-18T11:00:00Z'])->assertOk();
        $this->postJson(route('workouts.finish', $workout), ['ended_at' => '2026-10-18T12:30:00Z'])->assertOk();
        $this->assertSame('2026-10-18 19:00:00', Workout::sole()->ended_at->format('Y-m-d H:i:s'));

        $this->deleteJson(route('workouts.destroy', $workout))->assertOk();
        $this->deleteJson(route('workouts.destroy', $workout))->assertOk();
        $this->assertSame(0, Workout::count());
        $this->assertSame(1, Workout::withTrashed()->count());
    }

    public function test_validasi_set_dengan_pesan_indonesia(): void
    {
        [$workout] = $this->startWorkout();
        $we = $this->addExercise($workout);

        $this->putSet($workout, $we, Str::uuid()->toString(), ['kind' => 'super', 'weight_g' => -5, 'reps' => 5000])
            ->assertJsonValidationErrors([
                'kind' => 'Jenis set tidak dikenal.',
                'weight_g' => 'Berat harus antara 0 dan 1000 kg.',
                'reps' => 'Reps harus antara 0 dan 1000.',
            ]);
        $this->putSet($workout, Str::uuid()->toString(), Str::uuid()->toString())
            ->assertJsonValidationErrors(['workout_exercise' => 'Gerakan untuk set ini tidak ada di sesi.']);
    }

    public function test_halaman_latihan_berisi_sesi_aktif_dan_set_sebelumnya(): void
    {
        // Sesi lama yang sudah selesai → jadi "Sebelumnya".
        [$old] = $this->startWorkout();
        $oldWe = $this->addExercise($old);
        $this->putSet($old, $oldWe, Str::uuid()->toString(), ['weight_g' => 60000, 'reps' => 8])->assertOk();
        $this->putSet($old, $oldWe, Str::uuid()->toString(), ['sort' => 1, 'weight_g' => 60000, 'reps' => 7])->assertOk();
        $this->putSet($old, $oldWe, Str::uuid()->toString(), ['sort' => 2, 'done_at' => null])->assertOk(); // belum selesai: diabaikan
        $this->postJson(route('workouts.finish', $old), ['ended_at' => '2026-10-18T11:00:00Z']);

        // Sesi selesai berikutnya yang memuat gerakan ini tapi tanpa set selesai: acuan lama tetap dipakai.
        [$skipped] = $this->startWorkout(overrides: ['started_at' => '2026-10-18T13:00:00Z']);
        $skippedWe = $this->addExercise($skipped);
        $this->putSet($skipped, $skippedWe, Str::uuid()->toString(), ['weight_g' => 70000, 'done_at' => null])->assertOk();
        $this->postJson(route('workouts.finish', $skipped), ['ended_at' => '2026-10-18T14:00:00Z']);

        // Sesi yang dibuang tidak boleh jadi acuan.
        [$trashed] = $this->startWorkout(overrides: ['started_at' => '2026-10-19T10:00:00Z']);
        $trashedWe = $this->addExercise($trashed);
        $this->putSet($trashed, $trashedWe, Str::uuid()->toString(), ['weight_g' => 999000])->assertOk();
        $this->postJson(route('workouts.finish', $trashed), ['ended_at' => '2026-10-19T11:00:00Z']);
        $this->deleteJson(route('workouts.destroy', $trashed));

        [$active] = $this->startWorkout(overrides: ['started_at' => '2026-10-20T10:00:00Z']);
        $this->addExercise($active);

        $this->get(route('workouts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('workouts/Index')
                ->where('active.client_id', $active)
                ->has('active.exercises', 1)
                ->has("history.{$this->bench->id}.sets", 2)
                ->where("history.{$this->bench->id}.sets.0.weight_g", 60000)
                ->where("history.{$this->bench->id}.sets.1.reps", 7)
                ->where('recent.0', $this->bench->id)
                ->has('exercises', 1));
    }

    public function test_volume_tidak_menghitung_pemanasan_dan_set_belum_selesai(): void
    {
        $done = '2026-10-18 10:00:00';
        $sets = [
            ['kind' => 'warmup', 'weight_g' => 40000, 'reps' => 10, 'done_at' => $done],
            ['kind' => 'normal', 'weight_g' => 60000, 'reps' => 8, 'done_at' => $done],
            ['kind' => 'drop', 'weight_g' => 50000, 'reps' => 6, 'done_at' => $done],
            ['kind' => 'failure', 'weight_g' => 40000, 'reps' => 5, 'done_at' => $done],
            ['kind' => 'normal', 'weight_g' => 60000, 'reps' => 8, 'done_at' => null],
        ];

        // 60×8 + 50×6 + 40×5 = 480 + 300 + 200 = 980 kg·rep
        $this->assertSame(980 * 1000, WorkoutStats::volume($sets));
    }

    public function test_estimasi_1rm_epley_hanya_untuk_1_sampai_12_reps(): void
    {
        $this->assertSame(100000, WorkoutStats::estimatedOneRepMax(100000, 1));
        $this->assertSame(80000, WorkoutStats::estimatedOneRepMax(60000, 10)); // 60 × (1 + 10/30)
        $this->assertSame(74000, WorkoutStats::estimatedOneRepMax(60000, 7));  // 60 × 1,2333 = 74
        $this->assertNull(WorkoutStats::estimatedOneRepMax(60000, 13));
        $this->assertNull(WorkoutStats::estimatedOneRepMax(60000, 0));
        $this->assertNull(WorkoutStats::estimatedOneRepMax(null, 5));
    }
}

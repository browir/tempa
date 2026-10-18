<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HistoryAndPauseTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Exercise $bench;

    private Exercise $cycling;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->bench = $this->user->exercises()->create(['name' => 'Bench Press', 'muscle_group' => 'chest', 'equipment' => 'barbell', 'kind' => 'weight_reps']);
        $this->cycling = $this->user->exercises()->create(['name' => 'Cycling', 'muscle_group' => 'cardio', 'equipment' => 'bike', 'kind' => 'cardio']);
    }

    /** Sesi lengkap lewat endpoint outbox. */
    private function workout(User $user, string $start, ?string $end, array $sets = []): string
    {
        $id = Str::uuid()->toString();
        $this->actingAs($user)->postJson(route('workouts.store'), ['client_id' => $id, 'started_at' => $start])->assertSuccessful();
        foreach ($sets as [$exercise, $data]) {
            $we = Str::uuid()->toString();
            $this->postJson(route('workout-exercises.store', $id), ['client_id' => $we, 'exercise_id' => $exercise->id])->assertSuccessful();
            $this->putJson(route('sets.upsert', [$id, Str::uuid()->toString()]), [
                'workout_exercise' => $we, 'sort' => 0, 'kind' => 'normal', 'done_at' => $start, ...$data,
            ])->assertOk();
        }
        if ($end) {
            $this->postJson(route('workouts.finish', $id), ['ended_at' => $end])->assertOk();
        }

        return $id;
    }

    public function test_riwayat_hanya_sesi_selesai_milik_sendiri_terbaru_dulu(): void
    {
        $old = $this->workout($this->user, '2026-10-16T10:00:00Z', '2026-10-16T11:00:00Z', [[$this->bench, ['weight_g' => 60000, 'reps' => 8]]]);
        $ride = $this->workout($this->user, '2026-10-17T06:00:00Z', '2026-10-17T07:30:00Z', [[$this->cycling, ['distance_m' => 42300, 'duration_s' => 5400]]]);
        $trashed = $this->workout($this->user, '2026-10-18T09:00:00Z', '2026-10-18T09:30:00Z');
        $this->deleteJson(route('workouts.destroy', $trashed));
        $this->workout($this->user, '2026-10-18T10:00:00Z', null); // masih aktif

        $other = User::factory()->create();
        $this->workout($other, '2026-10-18T10:00:00Z', '2026-10-18T11:00:00Z');

        $this->actingAs($this->user)->get(route('history.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('history/Index')
                ->has('workouts', 2)
                ->where('workouts.0.client_id', $ride)
                ->where('workouts.0.distance_m', 42300)
                ->where('workouts.0.duration_s', 5400)
                ->where('workouts.1.client_id', $old)
                ->where('workouts.1.volume_g', 480000)
                ->where('workouts.1.sets_done', 1));
    }

    public function test_detail_riwayat_dan_kepemilikan(): void
    {
        $mine = $this->workout($this->user, '2026-10-16T10:00:00Z', '2026-10-16T11:00:00Z', [[$this->bench, ['weight_g' => 60000, 'reps' => 8]]]);

        $this->actingAs($this->user)->get(route('history.show', $mine))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('history/Show')
                ->where('workout.client_id', $mine)
                ->where('workout.duration_s', 3600)
                ->has('workout.exercises.0.sets', 1));

        $other = User::factory()->create();
        $theirs = $this->workout($other, '2026-10-16T10:00:00Z', '2026-10-16T11:00:00Z');
        $this->actingAs($this->user)->get(route('history.show', $theirs))->assertNotFound();
    }

    public function test_detail_sesi_yang_masih_aktif_diarahkan_ke_latihan(): void
    {
        $active = $this->workout($this->user, '2026-10-18T10:00:00Z', null);

        $this->actingAs($this->user)->get(route('history.show', $active))->assertRedirect(route('workouts.index'));
    }

    public function test_jeda_tidak_dihitung_ke_durasi(): void
    {
        $id = $this->workout($this->user, '2026-10-18T10:00:00Z', null);

        $this->putJson(route('workouts.update', $id), ['paused_at' => '2026-10-18T10:20:00Z', 'paused_seconds' => 0])->assertOk();
        $paused = Workout::sole();
        $this->assertSame('2026-10-18 18:20:00', $paused->paused_at->format('Y-m-d H:i:s'));
        $this->assertSame(1200, $paused->durationSeconds()); // berhenti bertambah selama dijeda

        // Lanjut setelah 10 menit, lalu selesai di menit ke-60: durasi bersih 50 menit.
        $this->putJson(route('workouts.update', $id), ['paused_at' => null, 'paused_seconds' => 600])->assertOk();
        $this->postJson(route('workouts.finish', $id), ['ended_at' => '2026-10-18T11:00:00Z', 'paused_seconds' => 600])->assertOk();

        $done = Workout::sole();
        $this->assertNull($done->paused_at);
        $this->assertSame(600, $done->paused_seconds);
        $this->assertSame(3000, $done->durationSeconds());
    }

    public function test_ubah_nama_tidak_mereset_jeda(): void
    {
        $id = $this->workout($this->user, '2026-10-18T10:00:00Z', null);
        $this->putJson(route('workouts.update', $id), ['paused_at' => null, 'paused_seconds' => 300])->assertOk();

        $this->putJson(route('workouts.update', $id), ['name' => 'Push Day', 'notes' => null])->assertOk();

        $this->assertSame('Push Day', Workout::sole()->name);
        $this->assertSame(300, Workout::sole()->paused_seconds);
    }
}

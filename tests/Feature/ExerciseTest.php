<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\User;
use App\Support\ExerciseCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExerciseTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function exercise(User $user, array $overrides = []): Exercise
    {
        return $user->exercises()->create([
            'name' => 'Bench Press', 'muscle_group' => 'chest', 'equipment' => 'barbell', 'kind' => 'weight_reps',
            ...$overrides,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return ['name' => 'Incline Bench Press', 'muscle_group' => 'chest', 'equipment' => 'barbell', 'kind' => 'weight_reps', 'notes' => null, ...$overrides];
    }

    public function test_tamu_tidak_bisa_membuka_daftar_gerakan(): void
    {
        $this->get(route('exercises.index'))->assertRedirect(route('login'));
    }

    public function test_daftar_hanya_berisi_gerakan_milik_sendiri_beserta_katalog(): void
    {
        $this->exercise($this->user, ['name' => 'Squat Saya', 'muscle_group' => 'quads']);
        $this->exercise(User::factory()->create(), ['name' => 'Gerakan Orang Lain']);

        $this->actingAs($this->user)->get(route('exercises.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('exercises/Index')
                ->has('exercises', 1)
                ->where('exercises.0.name', 'Squat Saya')
                ->where('exercises.0.archived', false)
                ->has('catalog.kinds', 4)
                ->where('catalog.muscleGroups.0', ['value' => 'chest', 'label' => 'Dada']));
    }

    public function test_daftar_diurutkan_per_kelompok_otot_sesuai_katalog(): void
    {
        $this->exercise($this->user, ['name' => 'Cycling', 'muscle_group' => 'cardio', 'equipment' => 'bike', 'kind' => 'cardio', 'sort' => 0]);
        $this->exercise($this->user, ['name' => 'Pull Up', 'muscle_group' => 'back', 'equipment' => 'bodyweight', 'kind' => 'bodyweight', 'sort' => 1]);
        $this->exercise($this->user, ['name' => 'Push Up', 'muscle_group' => 'chest', 'equipment' => 'bodyweight', 'kind' => 'bodyweight', 'sort' => 2]);

        $this->actingAs($this->user)->get(route('exercises.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('exercises.0.name', 'Push Up')
                ->where('exercises.1.name', 'Pull Up')
                ->where('exercises.2.name', 'Cycling'));
    }

    public function test_menambah_gerakan_kardio_sepeda(): void
    {
        $this->actingAs($this->user)
            ->post(route('exercises.store'), $this->payload(['name' => '  Gravel Ride ', 'muscle_group' => 'cardio', 'equipment' => 'bike', 'kind' => 'cardio']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $exercise = $this->user->exercises()->sole();
        $this->assertSame('Gravel Ride', $exercise->name);
        $this->assertSame('cardio', $exercise->kind);
    }

    public function test_nama_gerakan_unik_per_pengguna_tanpa_peduli_huruf_besar(): void
    {
        $this->exercise($this->user);

        $this->actingAs($this->user)
            ->post(route('exercises.store'), $this->payload(['name' => 'bench PRESS']))
            ->assertSessionHasErrors(['name' => 'Gerakan dengan nama ini sudah ada.']);

        // Nama yang sama milik pengguna lain tidak menghalangi.
        $this->exercise(User::factory()->create(), ['name' => 'Lunge']);
        $this->actingAs($this->user)
            ->post(route('exercises.store'), $this->payload(['name' => 'Lunge']))
            ->assertSessionHasNoErrors();
    }

    public function test_mengubah_gerakan_boleh_mempertahankan_namanya_sendiri(): void
    {
        $exercise = $this->exercise($this->user);

        $this->actingAs($this->user)
            ->put(route('exercises.update', $exercise), $this->payload(['name' => 'Bench Press', 'notes' => 'Pegangan lebar']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Pegangan lebar', $exercise->fresh()->notes);
        $this->assertSame('Bench Press', $exercise->fresh()->name);
    }

    public function test_validasi_jenis_otot_dan_alat_dengan_pesan_indonesia(): void
    {
        $this->actingAs($this->user)
            ->post(route('exercises.store'), $this->payload(['name' => '', 'kind' => 'distance', 'muscle_group' => 'neck', 'equipment' => 'rocket']))
            ->assertSessionHasErrors([
                'name' => 'Nama gerakan wajib diisi.',
                'kind' => 'Jenis pencatatan tidak dikenal.',
                'muscle_group' => 'Kelompok otot tidak dikenal.',
                'equipment' => 'Alat tidak dikenal.',
            ]);

        $this->assertSame(0, Exercise::count());
    }

    public function test_gerakan_milik_orang_lain_dianggap_tidak_ada(): void
    {
        $foreign = $this->exercise(User::factory()->create());

        $this->actingAs($this->user)->put(route('exercises.update', $foreign), $this->payload())->assertNotFound();
        $this->actingAs($this->user)->post(route('exercises.archive', $foreign))->assertNotFound();
        $this->actingAs($this->user)->post(route('exercises.unarchive', $foreign))->assertNotFound();

        $this->assertNull($foreign->fresh()->archived_at);
        $this->assertSame('Bench Press', $foreign->fresh()->name);
    }

    public function test_arsipkan_dengan_urungkan_lalu_pulihkan(): void
    {
        $exercise = $this->exercise($this->user);

        $this->actingAs($this->user)
            ->post(route('exercises.archive', $exercise))
            ->assertRedirect()
            ->assertInertiaFlash('toast.undo', route('exercises.unarchive', $exercise));

        $this->assertNotNull($exercise->fresh()->archived_at);
        $this->assertSame(0, $this->user->exercises()->active()->count());

        $this->actingAs($this->user)->post(route('exercises.unarchive', $exercise));
        $this->assertNull($exercise->fresh()->archived_at);
    }

    public function test_gerakan_bawaan_mencakup_gym_rumahan_dan_sepeda(): void
    {
        $added = ExerciseCatalog::seedFor($this->user);

        $this->assertSame(count(ExerciseCatalog::defaults()), $added);
        $names = $this->user->exercises()->pluck('kind', 'name');
        $this->assertSame('weight_reps', $names['Bench Press']);
        $this->assertSame('bodyweight', $names['Push Up']);
        $this->assertSame('bodyweight', $names['Pull Up']);
        $this->assertSame('time', $names['Plank']);
        $this->assertSame('cardio', $names['Cycling']);
        $this->assertSame('cardio', $names['Indoor Cycling']);
        $this->assertSame('cardio', $names['Running']);
        $this->assertSame('cardio', $names['Walking']);
    }

    public function test_gerakan_bawaan_valid_unik_dan_idempoten(): void
    {
        $defaults = collect(ExerciseCatalog::defaults());

        $this->assertSame($defaults->count(), $defaults->pluck(0)->map(fn ($n) => mb_strtolower($n))->unique()->count(), 'Nama bawaan ada yang ganda');
        foreach ($defaults as [$name, $muscle, $equipment, $kind]) {
            $this->assertArrayHasKey($muscle, ExerciseCatalog::MUSCLE_GROUPS, $name);
            $this->assertArrayHasKey($equipment, ExerciseCatalog::EQUIPMENT, $name);
            $this->assertArrayHasKey($kind, ExerciseCatalog::KINDS, $name);
        }

        ExerciseCatalog::seedFor($this->user);
        $this->assertSame(0, ExerciseCatalog::seedFor($this->user));
        $this->assertSame($defaults->count(), $this->user->exercises()->count());
    }

    public function test_gerakan_bawaan_tidak_ditambahkan_bila_pengguna_sudah_punya_gerakan(): void
    {
        $this->exercise($this->user, ['name' => 'Gerakan Sendiri']);

        $this->assertSame(0, ExerciseCatalog::seedFor($this->user));
        $this->assertSame(1, $this->user->exercises()->count());
    }
}

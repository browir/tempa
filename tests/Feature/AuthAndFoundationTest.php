<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthAndFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('settings'))->assertRedirect(route('login'));
    }

    public function test_halaman_masuk_bisa_dibuka(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/Login'));
    }

    public function test_tidak_ada_halaman_registrasi(): void
    {
        $this->get('/register')->assertNotFound();
        $this->get('/daftar')->assertNotFound();
    }

    public function test_pemilik_bisa_masuk_dan_keluar(): void
    {
        $user = User::factory()->create(['email' => 'pemilik@contoh.id']);

        $this->post(route('login.store'), ['email' => 'pemilik@contoh.id', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('auth.user.email', 'pemilik@contoh.id')
                ->missing('auth.user.password'));

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_sandi_salah_ditolak_dengan_pesan_indonesia(): void
    {
        User::factory()->create(['email' => 'pemilik@contoh.id']);

        $this->from(route('login'))
            ->post(route('login.store'), ['email' => 'pemilik@contoh.id', 'password' => 'salah'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Email atau kata sandi salah.']);
        $this->assertGuest();
    }

    public function test_percobaan_masuk_dibatasi(): void
    {
        User::factory()->create(['email' => 'pemilik@contoh.id']);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), ['email' => 'pemilik@contoh.id', 'password' => 'salah']);
        }

        $this->post(route('login.store'), ['email' => 'pemilik@contoh.id', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_halaman_pengaturan_bisa_dibuka(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('settings'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('settings/Index'));
    }

    /** env() Laravel membaca $_SERVER/$_ENV lebih dulu, jadi ketiganya diisi. */
    private function setOwnerEnv(array $values): void
    {
        foreach ($values as $key => $value) {
            $_ENV[$key] = $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }
    }

    public function test_seeder_membuat_pemilik_dari_env_dan_idempoten(): void
    {
        $this->setOwnerEnv(['OWNER_NAME' => 'Pemilik Tempa', 'OWNER_EMAIL' => 'owner@tempa.test', 'OWNER_PASSWORD' => 'rahasia-panjang']);

        try {
            $this->seed(DatabaseSeeder::class);
            $this->seed(DatabaseSeeder::class);
        } finally {
            $this->setOwnerEnv(['OWNER_NAME' => '', 'OWNER_EMAIL' => '', 'OWNER_PASSWORD' => '']);
        }

        $this->assertSame(1, User::where('email', 'owner@tempa.test')->count());
        $this->post(route('login.store'), ['email' => 'owner@tempa.test', 'password' => 'rahasia-panjang'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_seeder_tanpa_env_pemilik_tidak_membuat_akun(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, User::count());
    }

    public function test_ensure_schema_aman_di_sqlite(): void
    {
        $this->artisan('db:ensure-schema')
            ->expectsOutput('Tidak perlu membuat schema.')
            ->assertSuccessful();
    }

    public function test_search_path_pgsql_dibaca_dari_env(): void
    {
        // Lokal & tes memakai "public"; produksi menimpanya dengan DB_SEARCH_PATH=tempa (vercel.json).
        $this->assertSame('public', config('database.connections.pgsql.search_path'));
        $vercel = json_decode(file_get_contents(base_path('vercel.json')), true);
        $this->assertSame('tempa', $vercel['env']['DB_SEARCH_PATH']);
    }

    public function test_zona_waktu_wita(): void
    {
        $this->assertSame('Asia/Makassar', config('app.timezone'));
        $vercel = json_decode(file_get_contents(base_path('vercel.json')), true);
        $this->assertSame('Asia/Makassar', $vercel['env']['APP_TIMEZONE']);
    }

    public function test_ops_migrate_menolak_token_salah(): void
    {
        $this->get('/_ops/migrate/token-ngawur')->assertNotFound();
    }

    public function test_ops_migrate_dengan_token_benar_menjalankan_migrasi(): void
    {
        $token = hash_hmac('sha256', 'ops-migrate', (string) config('app.key'));

        $this->get('/_ops/migrate/'.$token)
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}

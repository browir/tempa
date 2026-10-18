<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Membuat akun pemilik. Idempoten: aman dijalankan berulang (mis. lewat /_ops/migrate).
     * Kredensial diambil dari OWNER_NAME / OWNER_EMAIL / OWNER_PASSWORD di .env.
     */
    public function run(): void
    {
        $email = env('OWNER_EMAIL');
        $password = env('OWNER_PASSWORD');

        // Tanpa nilai bawaan: jangan sampai produksi punya akun dengan sandi contoh.
        if (! $email || ! $password) {
            $this->command?->warn('OWNER_EMAIL / OWNER_PASSWORD belum diisi: akun pemilik tidak dibuat.');

            return;
        }

        User::firstOrCreate(
            ['email' => $email],
            ['name' => env('OWNER_NAME', 'Pemilik'), 'password' => $password],
        );
    }
}

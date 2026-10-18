<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
 * Tempa numpang di database Supabase Sisih; tabelnya ada di schema sendiri (DB_SEARCH_PATH).
 * Schema harus ada sebelum `migrate` membuat tabel migrations, jadi perintah ini dijalankan dulu:
 *   php artisan db:ensure-schema --env=supabase
 * Di SQLite / schema `public` tidak melakukan apa-apa.
 */
Artisan::command('db:ensure-schema', function () {
    $schema = (string) config('database.connections.pgsql.search_path');

    if (DB::getDriverName() !== 'pgsql' || $schema === 'public') {
        $this->info('Tidak perlu membuat schema.');

        return 0;
    }

    if (! preg_match('/^[a-z_][a-z0-9_]*$/', $schema)) {
        $this->error("Nama schema tidak valid: {$schema}");

        return 1;
    }

    DB::statement("CREATE SCHEMA IF NOT EXISTS \"{$schema}\"");
    $this->info("Schema \"{$schema}\" siap.");

    return 0;
})->purpose('Buat schema Postgres milik Tempa bila belum ada');

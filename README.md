# Tempa

Buku latihan pribadi: PWA pencatat latihan gym, rumahan, dan sepeda. Laravel 12 + Inertia + Vue 3 +
Tailwind v4, deploy di Vercel (`vercel-php`) dengan database Supabase Postgres.

Pedoman kerja lengkap ada di [CLAUDE.md](CLAUDE.md).

## Jalankan lokal (Windows + XAMPP)

```powershell
composer install; npm install
copy .env.example .env   # isi OWNER_NAME / OWNER_EMAIL / OWNER_PASSWORD
& "D:\xampp_baru\php\php.exe" artisan key:generate
& "D:\xampp_baru\php\php.exe" artisan migrate --seed
npm run build
& "D:\xampp_baru\php\php.exe" artisan serve --host=127.0.0.1 --port=8000
```

## Rilis

1. `php artisan test` hijau
2. `npm run build` — **`public/build` di-commit**, Vercel tidak membangun aset
3. Bila ada migrasi: `php artisan migrate --pretend --env=supabase`, lalu tanpa `--pretend`
   (contoh berkas di `.env.supabase.example`)
4. Commit & push

Cadangan migrasi produksi: `https://<domain>/_ops/migrate/<token>` dengan
token = `HMAC-SHA256("ops-migrate", APP_KEY)`.

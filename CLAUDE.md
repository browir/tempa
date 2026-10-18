# CLAUDE.md — Tempa (Workout Tracker)

Pedoman kerja Claude untuk project ini. Baca seluruhnya sebelum mengubah kode.
Arsitektur dan cara deploy diambil dari project **Sisih** (`D:\xampp_baru\htdocs\keuangan`), yang sudah
berjalan di Vercel + Supabase. Kalau ragu bagaimana sesuatu dikerjakan, lihat dulu cara Sisih
mengerjakannya, lalu ikuti polanya.

> Nama aplikasi: **Tempa** — "menempa" diri lewat repetisi & beban yang naik bertahap.
> Prefix localStorage: **`tempa:`**, nama cache: **`tempa-*-v1`**.

---

## 1. Produk

PWA pencatat latihan pribadi (satu pemilik), mobile-first, dipakai **di gym**:
- satu tangan, sela istirahat 1–3 menit, sinyal sering buruk, layar sering mati;
- mencatat satu set harus bisa dilakukan dalam ≤ 2 ketukan bila angkanya sama dengan set sebelumnya;
- data latihan **tidak boleh hilang** walau offline, tab tertutup, atau HP mati di tengah sesi.

Semua teks UI, URL, pesan validasi, komentar kode, nama tes dan pesan commit memakai **bahasa Indonesia**.
Identifier kode (class, method, variabel, kolom DB, nama route) memakai bahasa Inggris.

## 2. Stack (samakan versi dengan Sisih)

| Bagian | Pilihan |
|---|---|
| Backend | Laravel 12, PHP ^8.2 |
| Jembatan | Inertia (`inertiajs/inertia-laravel` ^3, `@inertiajs/vue3` ^3) — **tanpa API REST terpisah** |
| Frontend | Vue 3 `<script setup>` (JavaScript, bukan TypeScript), Vite 7, `@vitejs/plugin-vue` |
| Styling | Tailwind CSS v4 (`@tailwindcss/vite`), token warna di `resources/css/app.css` |
| Ikon / util | `lucide-vue-next`, `@vueuse/core`, font Geist (`@fontsource-variable/geist`, `geist-mono`) |
| Route di JS | Ziggy (`tightenco/ziggy` + `ziggy-js`), `@routes` di blade |
| DB lokal / tes | SQLite (`database/database.sqlite`; tes `:memory:`) |
| DB produksi | Supabase Postgres |
| Hosting | Vercel, runtime `vercel-php@0.8.0`, region `sin1` |

Jangan menambah dependensi besar (state manager, UI kit, chart library) tanpa bertanya. Grafik sederhana
dibuat sendiri dengan SVG seperti `DailyChart.vue` di Sisih.

## 3. Struktur folder

```
app/Http/Controllers/          controller tipis; Concerns/ untuk aturan validasi bersama
app/Http/Middleware/HandleInertiaRequests.php   props bersama (lazy closure)
app/Models/                    relasi selalu lewat User (user->workouts(), dst.)
app/Support/                   logika domain murni (mis. hitung 1RM, volume, PR)
resources/js/pages/            satu file per halaman Inertia (folder per fitur, huruf kecil)
resources/js/components/       komponen fitur; components/ui/ untuk komponen dasar
resources/js/composables/      useXxx.js (state & perilaku bersama)
resources/js/lib/              fungsi murni (format angka, tanggal, satuan)
resources/js/layouts/AppLayout.vue
public/sw.js, public/manifest.webmanifest, public/offline.html
api/index.php                  titik masuk Vercel
```

Alias `@` = `resources/js`. Halaman di-resolve dengan `import.meta.glob('./pages/**/*.vue', { eager: true })`;
halaman `auth/*` tanpa layout, sisanya memakai `AppLayout`.

## 4. Konvensi backend

- **Route**: URL bahasa Indonesia, nama route bahasa Inggris. Contoh:
  `GET /latihan` → `workouts.index`, `POST /latihan/{workout}/set` → `sets.store`,
  `/gerakan` (exercises), `/rutinitas` (routines), `/riwayat`, `/progres`, `/pengaturan`, `/masuk`, `/keluar`.
- **Kepemilikan data**: setiap tabel milik pengguna punya `user_id`. Di controller panggil
  `$this->ensureOwned($model)` (abort 404, bukan 403 — data orang lain diperlakukan seolah tidak ada).
  Validasi `exists` selalu dibatasi `->where('user_id', $userId)`.
- **Validasi**: `$request->validate([...], [pesan bahasa Indonesia])`. Aturan yang dipakai lebih dari satu
  controller dipindah ke trait di `Controllers/Concerns/`.
- **Respons aksi**: `Inertia::flash('toast', ['message' => '...'])` lalu `return back();`.
  Frontend menangkapnya lewat `router.on('flash', ...)` di `app.js`.
- **Props bersama** (`HandleInertiaRequests::share`): hanya data kecil yang dibutuhkan banyak halaman
  (mis. daftar gerakan untuk pemilih cepat, sesi aktif). Bungkus dengan closure `fn () => ...` agar lazy.
  Ingat: kolom baru yang dibaca di props bersama → semua halaman 500 kalau migrasi belum jalan di produksi.
- **Hapus**: pakai soft delete + toast "Urungkan" untuk data penting (sesi latihan, set).
- **Seeder** idempoten (`firstOrCreate`, cek `doesntExist()`), akun pemilik dari `OWNER_NAME/OWNER_EMAIL/OWNER_PASSWORD`;
  tanpa sandi bawaan. Seeder juga mengisi gerakan bawaan bila pengguna belum punya.
- **Query portabel SQLite ↔ Postgres**: hindari SQL mentah khusus satu DB. Pencarian teks pakai
  `whereRaw('lower(name) like ?', ['%'.mb_strtolower($q).'%'])` (Postgres `like` peka huruf besar).
  Hitung tanggal/periode di PHP (Carbon), bukan fungsi tanggal SQL. Di Postgres `GROUP BY` ketat —
  semua kolom non-agregat harus ada di `groupBy`.

## 5. Model domain (rancangan awal — sesuaikan, tapi pertahankan prinsipnya)

| Tabel | Kolom penting |
|---|---|
| `exercises` | user_id, name, muscle_group, equipment, kind (`weight_reps` / `reps` / `time` / `distance`), notes, archived_at, sort |
| `routines` | user_id, name, notes, sort — template latihan |
| `routine_exercises` | routine_id, exercise_id, sort, target_sets, target_reps, rest_seconds |
| `workouts` | user_id, routine_id?, name, started_at, ended_at?, notes, client_id (unique), soft deletes |
| `workout_exercises` | workout_id, exercise_id, sort, notes |
| `sets` | workout_exercise_id, sort, kind (`warmup` / `normal` / `drop` / `failure`), weight_g?, reps?, duration_s?, distance_m?, rpe?, done_at?, client_id (unique) |
| `body_logs` | user_id, logged_on, weight_g, notes |

Prinsip:
- **Angka disimpan sebagai integer** dalam satuan terkecil (seperti Sisih menyimpan rupiah): berat dalam
  **gram**, durasi dalam **detik**, jarak dalam **meter**. Konversi ke kg / menit / km hanya di tampilan
  (`resources/js/lib/units.js`). Jangan pakai float/decimal untuk berat.
- **Satu sesi aktif** per pengguna (`ended_at` null). Memulai sesi baru saat ada sesi aktif → tawarkan lanjutkan.
- **Volume** = Σ(berat × reps) set `normal`/`drop`/`failure` yang selesai; set `warmup` tidak dihitung.
- **Estimasi 1RM** (Epley): `berat × (1 + reps / 30)`, hanya untuk reps 1–12. Taruh di `app/Support/`.
- **Rekor pribadi (PR)** dihitung dari data (berat terberat, 1RM terbaik, volume set terbaik per gerakan),
  bukan disimpan manual. Simpan cache hanya kalau terbukti lambat.
- **"Sebelumnya"**: saat menambah set, tampilkan set yang sama dari sesi terakhir gerakan itu dan isi
  otomatis berat/reps dari sana.
- Tanggal & jam memakai `APP_TIMEZONE=Asia/Jakarta`; `APP_LOCALE=id`.

## 6. Konvensi frontend

- `<script setup>`, props dengan `defineProps({ ... })` bergaya objek, komentar singkat bahasa Indonesia
  hanya bila "kenapa"-nya tidak jelas.
- Navigasi & form lewat Inertia (`router.visit`, `useForm`), nama route lewat `route('...')` (Ziggy).
- **Desain**: salin pendekatan token Sisih — variabel CSS di `:root` dan `[data-theme='dark']`, dipetakan
  di `@theme inline`; kelas dasar `.card`, `.section-title`, `.page-title`, `shadow-card`, `shadow-lift`.
  Jangan menulis warna hex langsung di komponen. Tema (terang/gelap/sistem) dipasang oleh skrip inline
  di `app.blade.php` sebelum render agar tidak berkedip; tema gelap kemungkinan jadi favorit di gym.
- Teks kecil pakai utilitas `text-2xs` / `text-tiny` / `text-small` (lebih besar di layar sentuh lewat
  varian `touch`), bukan `text-[11px]`.
- **Di gym**: target sentuh ≥ 48px, aksi utama di zona jempol (bawah layar), stepper ± untuk berat
  (langkah mengikuti pengaturan, mis. 2,5 kg) dan reps, keypad angka sendiri di bottom sheet
  (lihat `Keypad.vue` + `Sheet.vue` di Sisih), angka besar dengan font mono.
- Sesi aktif: **Screen Wake Lock API** agar layar tidak mati, **timer istirahat** otomatis setelah set
  ditandai selesai (getar `navigator.vibrate` + notifikasi bila didukung), hormati `prefers-reduced-motion`.
- Bottom nav pill mengambang di HP; layout dua kolom di ≥1024px.
- Zoom dimatikan (meta viewport + `touch-action: pan-x pan-y` + pencegah gestur iOS di `app.js`), jadi
  semua teks & input harus tetap terbaca tanpa zoom.
- Gotcha iOS Safari: jangan memusatkan isi `<button>` dengan `grid place-items-center` (apalagi bersama
  `aspect-square`) — pakai `flex items-center justify-center` dan tinggi tetap.
- Gotcha bayangan: elemen ber-shadow di dalam baris `overflow-x-auto` terpotong — beri padding vertikal
  pada scroller ≥ jangkauan shadow.
- localStorage selalu memakai prefix `tempa:` dan dibungkus try/catch.

## 7. PWA & offline (wajib, inti produk)

- `public/sw.js` meniru Sisih: aset `/build/*` cache-first; navigasi & respons Inertia network-first
  dengan salinan terakhir; fallback `/offline.html`; abaikan `/_ops/*` dan request non-GET.
  Nama cache berversi (`tempa-assets-v1`, `-pages-v1`, `-data-v1`) — **naikkan versi** bila
  semantik cache berubah. Hapus cache halaman & data saat logout (lihat `app.js` Sisih).
- **Outbox**: setiap set/sesi baru dibuat dengan `client_id` (UUID dari klien, ada cadangan bila
  `crypto.randomUUID` tidak tersedia), disimpan dulu ke localStorage, lalu dikirim berurutan saat online.
  Server menolak duplikat lewat kolom `client_id` unik → kirim ulang selalu aman (idempoten).
- **Sesi aktif disimpan lokal** setiap perubahan, sehingga reload / tab tertutup / offline tidak
  menghilangkan set yang sudah diketik. Tampilkan bar status offline/antrean (seperti `OfflineBar.vue`).
- Manifest: `id`, `scope`, ikon biasa + maskable, `shortcuts` (mis. "Mulai latihan").

## 8. Deploy: Vercel + Supabase (salin dari Sisih, jangan improvisasi)

**Vercel tidak membangun frontend dan tidak menjalankan migrasi.**

1. `api/index.php` hanya `require __DIR__.'/../public/index.php';`.
2. `vercel.json`: salin dari Sisih apa adanya (installCommand/buildCommand berupa `echo`, `outputDirectory: public`,
   `regions: ["sin1"]`, function `api/index.php` dengan `vercel-php@0.8.0` dan `excludeFiles`, route
   `/build/*` immutable, `/` dan sisanya ke `/api/index.php`, env runtime: cache path ke `/tmp/*.php`,
   `VIEW_COMPILED_PATH=/tmp`, `LOG_CHANNEL=stderr`, `SESSION_DRIVER=cookie`, `SESSION_SECURE_COOKIE=true`,
   `CACHE_STORE=database`, `QUEUE_CONNECTION=sync`, `DB_CONNECTION=pgsql`, `DB_SSLMODE=require`).
3. `bootstrap/app.php`: `$middleware->trustProxies(at: '*')` agar HTTPS terbaca di belakang proxy Vercel.
4. Env rahasia di dashboard Vercel (Production): `APP_KEY`, `APP_NAME`, `DB_URL`, `OWNER_EMAIL`, `OWNER_PASSWORD`.
5. **`public/build` di-commit.** Setiap perubahan CSS/JS/Vue → `npm run build` lalu commit hasilnya
   (`.gitignore` tidak boleh mengabaikan `/public/build`). Lupa build = produksi menampilkan versi lama.
6. **Migrasi produksi manual**, dua cara:
   - (utama) dari lokal: `php artisan migrate --env=supabase` memakai `.env.supabase` (gitignored; berisi
     `APP_ENV, APP_KEY, DB_CONNECTION=pgsql, DB_SSLMODE=require, DB_URL, OWNER_*`). Cek dulu dengan `--pretend`.
     Jalankan **sebelum push** bila migrasinya aditif.
   - (cadangan) route `/_ops/migrate/{token}` dengan token = `HMAC-SHA256("ops-migrate", APP_KEY)`,
     dibandingkan dengan `hash_equals`, menjalankan `migrate --force` + `db:seed --force`, keluaran teks polos.
7. **RLS Supabase**: Supabase membuka tabel schema `public` lewat Data API. Setiap tabel baru harus
   `ENABLE ROW LEVEL SECURITY` (tanpa policy) di migrasinya — hanya bila driver `pgsql`. Laravel terhubung
   sebagai pemilik tabel sehingga tidak terdampak. Lihat migrasi `enable_row_level_security` di Sisih.
8. Koneksi: pakai **session pooler (port 5432)** di `DB_URL`. Bila memakai transaction pooler (6543),
   set `DB_EMULATE_PREPARES=true` (opsi PDO di `config/database.php` sudah disiapkan di Sisih).

## 9. Pengembangan lokal (Windows + XAMPP)

- Lokasi: `D:\xampp_baru\htdocs\tempa`. PHP: `D:\xampp_baru\php\php.exe` (tidak ada di PATH —
  di PowerShell: `& "D:\xampp_baru\php\php.exe" artisan ...`).
- Jalankan: `php artisan serve --host=127.0.0.1 --port=8000` (samakan dengan `APP_URL`), aset dari
  `npm run build` (tidak memakai Vite dev server).
- `artisan tinker` harus dengan `--execute="..."` dan stdin `< /dev/null`, kalau tidak akan menggantung.
- Cek tampilan: puppeteer-core + Chromium Playwright
  (`%LOCALAPPDATA%/ms-playwright/chromium-*/chrome-win64/chrome.exe`), jalankan node dengan
  `MSYS_NO_PATHCONV=1` di Git Bash, di luar sandbox. Periksa lebar 390px (HP) dan 1280px, tema terang & gelap.

## 10. Tes

- PHPUnit feature test (`tests/Feature`), SQLite in-memory (`phpunit.xml`), `RefreshDatabase`,
  nama method bahasa Indonesia: `test_set_pemanasan_tidak_dihitung_volume()`.
- Setiap fitur/aturan domain baru → tes. Wajib dites: kepemilikan data (404 untuk milik orang lain),
  dedupe `client_id`, perhitungan volume/1RM/PR, satu sesi aktif.
- Hapus/ganti `tests/Feature/ExampleTest.php` bawaan (di Sisih ia gagal karena `/` butuh login).
- Sebelum commit: `php artisan test` hijau dan `npm run build` sukses.

## 11. Aturan kerja untuk Claude

1. **Catat setiap perubahan** kode/konfigurasi/deploy ke memory changelog project (tanggal absolut, apa,
   di mana, kenapa) pada giliran yang sama. Ide baru → backlog di memory.
2. **Konfirmasi dulu** sebelum: menyentuh DB produksi (migrate `--env=supabase`, query ke Supabase),
   `git push`, menghapus data, atau menambah dependensi.
3. Urutan aman rilis: tes → `npm run build` → (bila ada migrasi) migrasi produksi atas izin → commit
   termasuk `public/build` → push.
4. Pesan commit bahasa Indonesia, kalimat perintah singkat (mis. "Tambah timer istirahat di sesi aktif").
5. Ikuti pola yang sudah ada sebelum membuat pola baru; kalau membuat komponen dasar baru, taruh di
   `components/ui/` dan pakai ulang.
6. Laporkan apa adanya: bila tes gagal atau langkah dilewati (mis. screenshot tidak bisa dijalankan), katakan.

## 12. Definition of done

- [ ] Berfungsi di HP 390px dan desktop, tema terang & gelap
- [ ] Bisa dipakai offline (atau jelas ditandai bila memang butuh online)
- [ ] Data dibatasi per `user_id`, ada tes
- [ ] Migrasi baru: RLS untuk tabel baru, aman di SQLite & Postgres, sudah `--pretend`
- [ ] `php artisan test` hijau, `npm run build` dijalankan & `public/build` ikut di-commit
- [ ] Changelog memory diperbarui

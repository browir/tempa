# Prompt awal untuk sesi Claude pertama di project Workout Tracker

Cara pakai: buat folder KOSONG `D:\xampp_baru\htdocs\tempa`, buka di VS Code, mulai Claude Code di sana
(agar memory Claude tersimpan untuk project Tempa), lalu salin teks di bawah garis ke chat.

---

Aku mau membuat aplikasi baru: **workout tracker** PWA pribadi bernama **Tempa**, di folder
`D:\xampp_baru\htdocs\tempa`. Arsitekturnya meniru project keuanganku **Sisih** di
`D:\xampp_baru\htdocs\keuangan` (Laravel 12 + Inertia + Vue 3 + Tailwind v4, deploy Vercel + Supabase).

Pedoman lengkapnya ada di `D:\xampp_baru\htdocs\pedoman-workout-tracker\CLAUDE.md` — baca itu dulu,
lalu baca file-file Sisih yang dirujuk di dalamnya (`vercel.json`, `api/index.php`, `bootstrap/app.php`,
`config/database.php`, `HandleInertiaRequests.php`, `resources/js/app.js`, `resources/css/app.css`,
`app.blade.php`, `public/sw.js`, `useOutbox.js`, `components/ui/*`, migrasi RLS, seeder, `routes/web.php`
bagian `/_ops/migrate`).

**Tahap 0 — fondasi (kerjakan sekarang):**
1. Buat project Laravel 12 langsung di folder ini (masih kosong; `composer create-project laravel/laravel .`; PHP ada di `D:\xampp_baru\php\php.exe`, tidak di PATH).
2. Pasang Inertia, Vue 3, Tailwind v4, Ziggy, lucide, vueuse, font Geist dengan versi yang sama seperti Sisih.
3. Salin `CLAUDE.md` pedoman ke root project (nama Tempa & prefix `tempa:` sudah terisi).
4. Siapkan konfigurasi deploy yang sama dengan Sisih: `vercel.json`, `api/index.php`, trustProxies,
   opsi PDO pgsql, `.gitignore` yang meng-commit `public/build`, contoh `.env.supabase` (tanpa rahasia),
   route `/_ops/migrate/{token}`, migrasi RLS.
5. Login pemilik saja (tanpa registrasi), seeder pemilik dari `OWNER_*`, `APP_LOCALE=id`, `APP_TIMEZONE=Asia/Jakarta`.
6. Layout dasar: token warna (terang/gelap), AppLayout dengan bottom nav pill, Sheet, Toaster, PWA
   (manifest, sw.js, offline.html), halaman beranda kosong.
7. `git init`, tes hijau, `npm run build`, commit pertama. Jangan push sebelum aku setujui.
8. Buat memory project: ringkasan project, changelog, backlog, dan aturan "selalu catat perubahan".

**Tahap 1 — MVP (setelah fondasi beres, satu per satu, tunjukkan rencana tiap tahap dulu):**
1. Daftar gerakan (exercise) + gerakan bawaan dari seeder, cari & filter per otot.
2. Sesi latihan aktif: mulai kosong / dari rutinitas, tambah gerakan, catat set (berat, reps, jenis set),
   isi otomatis dari sesi sebelumnya, tandai selesai, timer istirahat, wake lock, selesai & ringkasan.
3. Offline penuh untuk sesi aktif (simpan lokal + outbox `client_id`).
4. Rutinitas/template latihan.
5. Riwayat sesi + detail, ubah/hapus dengan urungkan.

**Tahap 2 — progres:**
grafik per gerakan (berat terberat, estimasi 1RM, volume), rekor pribadi + perayaan saat PR,
kalender/streak latihan, log berat badan, statistik mingguan per kelompok otot.

**Tahap 3 — nanti:** superset, plate calculator, ekspor CSV, pengingat jadwal latihan.

Sebelum mulai Tahap 0, tanyakan hal yang benar-benar belum jelas (mis. satuan kg/lb,
apakah perlu multi-user), selebihnya pakai default dari pedoman.

# Fase 0: membangun fondasi proyek

Dikerjakan sekali di awal, dengan `/bootstrap`. Tanpa tanggal dan tanpa estimasi (CLAUDE.md M15). Hentikan dan tanya pengguna bila langkah mana pun butuh sesuatu di luar daftar yang disetujui.

## Prasyarat (diperiksa, bukan dipasang diam-diam)

PHP 8.3 atau lebih baru (Laravel 13 mensyaratkannya), Composer 2, Node LTS dan npm, Git, Docker (untuk MySQL lokal), Python 3. Bila ada yang kurang, laporkan ke pengguna.

## Langkah

1. **Layanan lokal.** `docker compose up -d` memakai `compose.yaml` (MySQL 8 untuk `sikg` dan `sikg_test`, Redis). Dilarang SQLite. Redis hanya untuk mencoba Profil A; aplikasi harus berjalan dan lulus tes tanpa Redis (M21).
2. **Proyek Laravel 13.** Buat di folder sementara di luar repositori ini, lalu salin berkasnya ke akar repositori tanpa menimpa berkas kit (`CLAUDE.md`, `docs/`, `.claude/`, `scripts/`, `tooling/`, `.github/`, `tests-templates/`, `compose.yaml`, `.gitignore`, `README.md`). Gabungkan `.gitignore` dengan `tooling/templates/gitignore`.
   - Pilihan A (disukai bila kompatibel): starter kit resmi Laravel untuk React dengan Inertia. Periksa kompatibilitas versi Inertia dengan Laravel 13 (PRD bagian 9). Buang bagian yang tidak dipakai (misalnya WorkOS, halaman demo).
   - Pilihan B: instal manual `inertiajs/inertia-laravel`, React, TypeScript, Vite, Tailwind CSS 4 (`@tailwindcss/vite`, CSS-first).
   - Starter kit sering membawa paket tambahan. Jalankan `python3 scripts/guard.py`; semua paket yang ditandai `[DEP]` didaftarkan ke pengguna dalam **satu pesan** beserta alasan, untuk disetujui dan ditambahkan ke `tooling/approved-dependencies.json` oleh pengguna.
3. **Konfigurasi dasar.**
   - Salin `tooling/templates/env.example` menjadi `.env.example`; salin `phpunit.xml`, `vitest.config.ts`, `playwright.config.ts` dari `tooling/templates/` ke akar. Claude Code tidak membuat, membaca, atau mengisi `.env`: minta pengguna menjalankan `cp .env.example .env && php artisan key:generate` dan mengisi kata sandi database lokalnya sendiri.
   - Atur `tsconfig.json` agar `extends` ke `./tooling/tsconfig.base.json`.
   - Atur bawaan `.env.example` agar kompatibel hosting bersama: `CACHE_STORE=database`, `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`, `LOG_CHANNEL=daily`, `LOG_DAILY_DAYS=90`, `SIKG_HOSTING_PROFILE=shared`. Tambahkan variabel itu ke `config/` (bukan `env()` langsung di kode). Buat migrasi tabel framework (`sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`) dan `system_heartbeats` (`docs/rules/database.md`).
   - Zona waktu aplikasi UTC; zona waktu klinik dari pengaturan (SYS-04). Locale `id`.
4. **Skrip.** Gabungkan `tooling/templates/composer-scripts.json` (autoload `Src\\` ke `src/` dan skrip) ke `composer.json`, dan `tooling/templates/package-scripts.json` ke `package.json`.
5. **Paket yang disetujui.** Instal sesuai `tooling/approved-dependencies.json` (backend: spatie/laravel-permission, maatwebsite/excel, PDF salah satu dari spatie/laravel-pdf atau barryvdh/laravel-dompdf, larastan, pint, deptrac, pest; frontend: @inertiajs/react, @tanstack/react-query, zod, react-hook-form, @hookform/resolvers, vitest, testing-library, playwright). `composer require` dan `npm install <paket>` akan meminta persetujuan pengguna; itu disengaja. PDF memakai `barryvdh/laravel-dompdf` (bukan `spatie/laravel-pdf`, yang butuh Chromium dan tidak jalan di shared hosting; ADR-0006). Pilihan 2FA (AUTH-05) dicatat sebagai ADR baru.
6. **Kerangka konteks.** `scripts/make-context.sh Shared IdentityAccess Clinic Patient Scheduling Clinical Pharmacy Billing Reporting` (satu per satu). Isi `Shared` dengan: `Money`, `Clock` (+ implementasi sistem dan palsu untuk tes), `IdGenerator` (ULID), `Transaction`, `DomainEvent` dan dispatcher setelah commit, `AuditLogger`, `DomainException` dengan kode bisnis, `Sequence` (tabel `sequences` dengan kunci baris).
7. **Pengaman.** Salin templat dari `tests-templates/` yang sudah relevan: `Architecture/ArchitectureTest.php`, `Unit/MoneyTest.php`. Jalankan `vendor/bin/deptrac analyse --config-file=tooling/deptrac.yaml`. Bila sintaks Deptrac pada versi terpasang berbeda, laporkan; jangan melonggarkan aturan.
8. **Inti keamanan.** Autentikasi (AUTH-01, AUTH-04) dengan batas percobaan dan sesi kedaluwarsa; peran dan izin granular (AUTH-02) dengan seeder peran dari matriks PRD bagian 4; global scope `clinic_id`/`branch_id` dan tesnya; header keamanan, CSP, HSTS; `access_logs` dan `audit_logs` (hanya INSERT).
9. **Kerangka frontend.** `resources/js/{app.tsx,Pages,features,components/ui,lib}` sesuai `docs/rules/frontend.md`: QueryClientProvider, peta galat Zod berbahasa Indonesia, `lib/http` (satu-satunya pemanggil `fetch`, CSRF, penanganan 401/403/409/419/422), `lib/format` (rupiah dan tanggal DD/MM/YYYY), `can()`.
10. **Kompatibilitas hosting.** Tambahkan arch test (tanpa `exec`/`shell_exec`/`system`/`passthru`/`proc_open`/`popen`/backtick dan tanpa `Redis` facade di `src/` dan `app/`) di `tests/Architecture/`. Siapkan *usulan* untuk berkas terlindungi di `docs/OPEN-QUESTIONS.md` (job CI "hosting bersama" tanpa Redis dan dengan `disable_functions`, langkah CI pembuat arsip rilis siap unggah berisi `vendor/` dan `public/build`, aturan `HOST` di `guard.py`, skrip cadangan Profil B); pengguna yang menerapkannya.
11. **Verifikasi.** `python3 scripts/selftest.py`, lalu `scripts/verify.sh`. Semua harus lulus. Commit: `chore: fondasi proyek (Fase 0)`.
12. **Lanjut.** Kerjakan AUTH dan MST (konteks IdentityAccess dan Clinic) dengan `/next`. Gerbang 1 (PRD dan prototipe disetujui pemilik) harus dinyatakan pengguna sebelum Fase 1a dimulai; bila belum, tanyakan.

## Di luar tanggung jawab Claude Code

Pendaftaran PSE dan akun SATUSEHAT oleh klinik, pemilihan penyedia hosting, pengaturan server produksi, rilis ke produksi. Claude Code menyiapkan kode dan dokumen; manusia mengerjakan sisanya (`docs/operations.md`).

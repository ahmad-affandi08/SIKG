# SIKG Development Kit

Paket pengembangan untuk Sistem Informasi Klinik Gigi (klinik gigi umum, non-BPJS): Laravel 13 (DDD, modular monolith), MySQL 8, Inertia + React + TypeScript, Tailwind CSS 4, Zod, TanStack Query. Dapat dijalankan di VPS maupun shared hosting (cPanel atau sejenis) dengan kode yang sama; lihat bagian Hosting di bawah.

Kit ini dibuat agar seluruh sistem dikerjakan Claude Code tanpa melanggar aturan yang sudah ditetapkan di PRD. Aturannya tidak hanya ditulis; sebagian besar **ditegakkan otomatis** oleh hook, skrip pemeriksa, dan CI.

## Isi

| Lokasi | Fungsi |
| --- | --- |
| `CLAUDE.md` | Aturan Mutlak (M1 sampai M21) yang dibaca Claude Code setiap sesi |
| `docs/rules/` | Aturan rinci: alur kerja, backend DDD, database, frontend, keamanan dan privasi, pengujian dan Definition of Done, hosting (server dan shared hosting) |
| `docs/PRD/` | PRD lengkap dalam 16 file Markdown, tanpa tanggal dan tanpa estimasi durasi |
| `docs/STATUS.md` | Daftar 84 kebutuhan (ID, prioritas) yang dicentang Claude Code saat selesai |
| `docs/adr/` | Catatan keputusan arsitektur awal (ADR-0001 sampai 0006) dan templat |
| `docs/BOOTSTRAP.md` | Langkah Fase 0: membangun fondasi proyek |
| `docs/operations.md` | Lingkungan, rilis (server dan shared hosting), cron, cadangan, insiden (dikerjakan manusia) |
| `docs/OPEN-QUESTIONS.md` | Tempat Claude Code mencatat konflik dan pertanyaan untuk Anda |
| `.claude/settings.json` | Izin, daftar tolak, dan hook |
| `.claude/commands/` | `/bootstrap`, `/next`, `/implement <ID>`, `/review-rules`, `/new-context`, `/adr` |
| `.claude/agents/` | `rules-reviewer`, `security-reviewer`, `test-writer`, `migration-reviewer` |
| `scripts/` | `guard.py` (pemeriksa aturan), `verify.sh` (gerbang penuh), `hooks/`, `make-context.sh`, `status.py`, `seal.py`, `selftest.py` |
| `tooling/` | Konfigurasi Deptrac, Larastan, Pint, ESLint, Prettier, tsconfig; daftar dependensi yang disetujui; templat phpunit, env, vitest, Playwright |
| `tests-templates/` | Templat tes: arsitektur, uang, matriks otorisasi, IDOR, audit, kunci catatan klinis, idempotensi, konkurensi, Zod, odontogram, E2E |
| `.github/workflows/ci.yml` | CI: guard, backend, frontend, E2E, kompatibilitas shared hosting, dan arsip rilis siap unggah |
| `compose.yaml` | MySQL 8 dan Redis untuk pengembangan lokal (Redis opsional; aplikasi tidak boleh bergantung padanya) |

## Cara memakai

1. Ekstrak zip ke folder repositori baru, lalu `git init` dan commit awal.
2. Pastikan Python 3 terpasang, lalu `python3 scripts/selftest.py` (harus "SELFTEST LULUS").
   Claude Code tidak menyentuh `.env`. Setelah proyek Laravel ada, Anda sendiri yang menjalankan `cp .env.example .env && php artisan key:generate` dan mengisi kata sandi database lokal.
3. Buka Claude Code di folder ini dan jalankan `/bootstrap`. Claude Code membangun fondasi (Laravel 13, Inertia React, Tailwind 4, CI, kerangka konteks) dan meminta persetujuan Anda untuk setiap dependensi.
4. Setelah fondasi lulus `scripts/verify.sh`, jalankan `/next` berulang. Urutan kerja: semua Must Fase 1, lalu Should, Could, lalu Fase 2 (P2-01 SATUSEHAT lebih dulu) dan Fase 3. Fase berikutnya tidak dimulai tanpa persetujuan Anda pada gerbangnya.
5. Pantau kemajuan dengan `python3 scripts/status.py` atau membuka `docs/STATUS.md`.

## Bagaimana aturan ditegakkan

| Aturan | Penegak |
| --- | --- |
| Lapisan DDD dan batas konteks | `tooling/deptrac.yaml`, tes arsitektur, `guard.py` (DOMAIN, LAYER, XCTX, STRUCT) |
| Uang bilangan bulat, tanpa float | `guard.py` (MONEY), tes `Money`, kolom `*_amount` BIGINT |
| Tanpa hapus permanen, FK RESTRICT | `guard.py` (DESTRUCT), hook migrasi |
| Otorisasi, audit, IDOR | `guard.py` (AUTHZ), templat tes matriks, scope, audit |
| PHI tidak di URL, log, console, storage peramban, data uji | `guard.py` (PHI, M12, SECRET), ESLint, tes log |
| Dokumen aturan tidak diubah agen | Hook `pre_edit`/`pre_bash`, deny di `settings.json`, segel checksum |
| Tidak melemahkan tes dan lint | `guard.py` (WEAK, TS), hook, ESLint, PHPStan tanpa baseline |
| Dependensi baru perlu persetujuan | `tooling/approved-dependencies.json` + `guard.py` (DEP) + `ask` pada composer/npm |
| Perintah berbahaya (force push, migrate:fresh, rm -rf, ssh, baca .env) | Hook `pre_bash` dan daftar tolak |
| Tanpa tanggal dan estimasi waktu | `guard.py` (DATE) pada dokumen kerja, komentar, dan pesan commit |
| Sesi tidak selesai saat ada pelanggaran | Hook `Stop` menjalankan `guard.py` |
| Selesai berarti terverifikasi | `scripts/verify.sh`, CI, `guard.py` (REQID: ID selesai wajib punya tes) |

## Mengubah aturan atau PRD (oleh Anda)

File terlindungi (`CLAUDE.md`, `docs/rules/`, `docs/PRD/`, `docs/operations.md`, `docs/BOOTSTRAP.md`, `tests-templates/`, `.claude/`, `scripts/`, `tooling/`, `.github/`, `compose.yaml`) tidak bisa diubah Claude Code. Ubah sendiri di editor Anda, lalu:

```
python3 scripts/seal.py        # perbarui segel checksum
python3 scripts/selftest.py    # pastikan pengaman masih utuh
```

Menyetujui dependensi: tambahkan namanya ke `tooling/approved-dependencies.json` (lalu `seal.py`).

## Hosting: VPS atau shared hosting

SIKG mendukung dua profil dengan kode yang sama (ADR-0006, `docs/rules/hosting.md`):

| | Profil A: server | Profil B: shared hosting |
| --- | --- | --- |
| Cocok untuk | Anggaran dan keahlian operasional ada; ingin RPO 1 jam dan Pulse | Klinik kecil (1-3 dokter), biaya rendah |
| Syarat | VPS, Nginx, PHP-FPM 8.3+, MySQL 8 | PHP 8.3+, MySQL 8, cron, `.htaccess`, akar dokumen ke `public/` (daftar lengkap di `docs/rules/hosting.md` bagian 2) |
| Antrean dan scheduler | Supervisor + cron | Cron saja (`schedule:run` dan `queue:work --stop-when-empty`) |
| Cadangan | Dump harian + binlog | Dump berkala lewat cron, dienkripsi, dikirim keluar server |

Pada shared hosting server tidak menjalankan `npm`/`composer install`: CI (job `release-package`) membuat arsip rilis siap unggah, dan `scripts/verify.sh --shared` menguji kompatibilitasnya secara lokal. Setelah rilis, buka halaman Kesiapan Lingkungan (SYS-06) untuk memastikan versi PHP, ekstensi, izin tulis, cron, dan antrean benar. Keterbatasan yang harus Anda setujui ada di `docs/rules/hosting.md` bagian 4 dan `docs/OPEN-QUESTIONS.md` (Q-1 sampai Q-3).

## Hal yang perlu Anda ketahui

- Kit ini tidak berisi kode aplikasi; Claude Code membangunnya mengikuti aturan di sini.
- Konfigurasi Deptrac ditulis untuk seri 2.x. Bila versi terpasang memakai sintaks lain, Claude Code akan melapor, bukan melonggarkan aturan.
- Kewajiban PSE dan SATUSEHAT (Permenkes 24/2022) diurus klinik secara paralel. PRD bukan nasihat hukum; konfirmasikan ke dinas kesehatan atau konsultan hukum sebelum go-live.
- Pertanyaan bisnis yang masih terbuka ada di `docs/PRD/09-risiko-pertanyaan-terbuka.md`.
- Draf pemeriksaan disimpan di server, bukan di penyimpanan peramban (penyesuaian dari PRD awal agar sejalan dengan aturan privasi M11).

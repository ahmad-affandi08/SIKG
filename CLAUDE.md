# SIKG: Sistem Informasi Klinik Gigi (aturan untuk Claude Code)

Aplikasi web rekam medis elektronik dan operasional klinik gigi umum non-BPJS. Seluruh pengembangan dikerjakan Claude Code dengan mengikuti dokumen di repositori ini. Baca file ini sampai habis sebelum mengubah apa pun.

## Sumber kebenaran dan urutan prioritas

1. Perintah eksplisit pengguna di sesi ini. Jika perintah itu bertentangan dengan Aturan Mutlak di bawah, berhenti, jelaskan konfliknya, dan minta persetujuan tertulis sebelum melanjutkan.
2. `CLAUDE.md` (file ini)
3. `docs/rules/*.md`
4. `docs/PRD/*.md` (kebutuhan dengan ID seperti `PAS-01`)
5. `docs/adr/*.md`

Dokumen di atas **tidak boleh diubah oleh Claude Code** kecuali pengguna memintanya secara eksplisit. Bila menemukan konflik, celah, atau kebutuhan yang ambigu: catat di `docs/OPEN-QUESTIONS.md`, tanyakan ke pengguna, jangan menebak.

## Wajib dibaca sebelum menyentuh area tertentu

| Area yang disentuh | Baca dulu |
| --- | --- |
| Alur kerja, commit, definisi selesai | `docs/rules/workflow.md`, `docs/rules/testing-dod.md` |
| `src/**` (backend) | `docs/rules/backend-ddd.md`, `docs/rules/database.md` |
| `resources/js/**` (frontend) | `docs/rules/frontend.md` |
| Data pasien, login, izin, berkas, log | `docs/rules/security-privacy.md` |
| Kebutuhan fitur | `docs/PRD/` file yang memuat ID-nya (lihat `docs/STATUS.md`) |
| Server, hosting, cadangan, rilis | `docs/operations.md`, `docs/rules/hosting.md` |
| Antrean, scheduler, cache, sesi, berkas unggahan, PDF, Excel, polling | `docs/rules/hosting.md` |
| Fondasi proyek (Fase 0) | `docs/BOOTSTRAP.md` |
| Menulis tes | `docs/rules/testing-dod.md`, templat di `tests-templates/` |

## Aturan Mutlak

Pelanggaran aturan ini adalah cacat, bukan gaya. Skrip `scripts/guard.sh` dan hook di `.claude/` memeriksa sebagian besar secara otomatis; sisanya menjadi tanggung jawab Anda.

**Stack dan arsitektur**

- **M1. Stack tetap:** PHP 8.3 atau lebih baru, Laravel 13, MySQL 8, Inertia.js + React + TypeScript strict, Tailwind CSS 4, Zod, TanStack Query. Dilarang menambah framework, ORM, pustaka UI besar, atau state manager global (Redux, Zustand, dsb.) tanpa ADR yang disetujui pengguna. Dependensi baru apa pun: tulis alasan singkat dan minta persetujuan. Redis, Supervisor, dan Node di server bukan bagian stack wajib (lihat M21).
- **M2. DDD modular monolith:** kode backend hidup di `src/<Context>/{Domain,Application,Infrastructure,Presentation}`. Domain tidak boleh memakai `Illuminate\*`, Eloquent, request, atau facade. Controller hanya memanggil satu use case. Antar-konteks hanya lewat ID, `Contracts`, dan domain event.
- **M3. Struktur data:** MySQL, kunci primer ULID, foreign key `ON DELETE RESTRICT`, semua tabel transaksional memiliki `clinic_id` dan `branch_id` dan setiap query ter-scope.

**Uang, klinis, dan keuangan**

- **M4. Uang** selalu value object `Money` (bilangan bulat rupiah, BIGINT). Dilarang `float`, `double`, `decimal`, atau `number` desimal untuk uang. Pembulatan hanya di satu tempat (`Money`).
- **M5. Tidak ada hapus permanen** data klinis dan keuangan lewat aplikasi. Pakai soft delete, void, refund, atau addendum. Dilarang `forceDelete`, `truncate`, atau `DELETE FROM` pada tabel klinis dan keuangan.
- **M6. Catatan klinis terkunci:** setelah kunjungan ditutup, isi hanya bisa berubah lewat addendum. Perbaikan langsung hanya oleh pengguna berwenang dalam 2x24 jam sejak input (RM-07).
- **M7. Tagihan:** total, jumlah dibayar, dan status diturunkan oleh domain, bukan diisi manual. Tarif disalin ke item tagihan saat dibuat (MST-08). Pembayaran memakai `Idempotency-Key`.
- **M8. Odontogram** memakai penomoran FDI; satu snapshot per kunjungan; kondisi gigi dari tabel master `tooth_conditions`.

**Keamanan dan privasi**

- **M9. Otorisasi di server:** setiap aksi dicek dengan Policy dan permission sesuai matriks hak akses di `docs/PRD/00-ringkasan-lingkup-persona.md`. Menyembunyikan menu di UI bukan keamanan.
- **M10. Audit:** perubahan data sensitif dan pembukaan rekam medis pasien tercatat (siapa, kapan, sebelum dan sesudah). Log audit tidak bisa diubah lewat aplikasi.
- **M11. Data pribadi pasien (PHI):** NIK dan nomor HP dienkripsi di aplikasi dengan kolom hash (blind index) untuk pencarian. Dilarang menaruh PHI di URL, query string, log, `console`, pelacak galat tanpa scrubbing, atau data uji. NIK wajib saat registrasi pasien (alasan dicatat bila belum ada).
- **M12. Tidak ada data pasien nyata** di repositori, seed, fixture, atau tes. Pakai data sintetis.
- **M13. Rahasia:** jangan membaca, menulis, atau mencetak `.env` atau kunci. Jangan commit rahasia. Hanya `.env.example` yang boleh diubah.

**Cakupan dan proses**

- **M14. Kerjakan hanya yang ada di PRD,** dengan ID. Di luar lingkup (jangan dibuat): BPJS, integrasi DICOM/PACS, akuntansi penuh, payroll, telemedisin, payment gateway pada Fase 1. Jangan mengerjakan Fase 2 atau 3 sebelum semua kebutuhan Must Fase 1 selesai, kecuali pengguna meminta.
- **M15. Tanpa tanggal dan tanpa estimasi waktu.** Jangan menulis tanggal target, estimasi hari atau minggu, jadwal, atau durasi di dokumen, rencana, komentar kode, pesan commit, maupun tanggapan. Pengguna ingin pekerjaan berjalan secepat mungkin berdasarkan urutan prioritas, bukan berdasarkan jadwal.
- **M16. Jangan melemahkan pengaman:** dilarang menonaktifkan, melewati, atau mengendurkan tes, lint, PHPStan, Deptrac, `scripts/guard.sh`, hook, atau konfigurasi di `tooling/` demi meloloskan pekerjaan. Bila aturan menghalangi, berhenti dan tanya.
- **M17. Perintah berbahaya dilarang:** `migrate:fresh`, `migrate:reset`, `db:wipe` di luar lingkungan lokal; `rm -rf` di luar folder sementara; `git push --force`; `git reset --hard` pada pekerjaan yang belum di-commit; mengubah konfigurasi produksi.
- **M18. Bahasa:** antarmuka dan pesan galat berbahasa Indonesia; format tanggal DD/MM/YYYY; uang `Rp 1.250.000`. Nama kelas, variabel, tabel, dan route berbahasa Inggris. Dokumen berbahasa Indonesia.
- **M19. Selesai berarti terverifikasi:** pekerjaan baru dianggap selesai bila `scripts/verify.sh` lulus dan Definition of Done di `docs/rules/testing-dod.md` terpenuhi. Jangan melaporkan "selesai" bila ada tes gagal, dilewati, atau belum dijalankan.
- **M20. Integrasi SATUSEHAT** dikerjakan di Fase 2 (P2-01), tetapi model data dan kode standar harus siap sejak Fase 1. Jangan membuat integrasi eksternal ad hoc sebelum itu.
- **M21. Satu kode, dua profil hosting (server dan shared hosting).** Kode harus berjalan di shared hosting tanpa perubahan, hanya konfigurasi (`docs/rules/hosting.md`, ADR-0006). Dilarang bergantung pada Redis (hanya opsi konfigurasi), proses daemon (Supervisor, Horizon, Octane, Reverb, WebSocket), Node/npm di server, `exec`/`shell_exec`/`proc_open`/`system`/`passthru`/`popen`, symlink (`storage:link`), trigger atau stored procedure MySQL, dan akses root. Bawaan: driver `database` untuk cache, sesi, antrean; antrean dan scheduler digerakkan cron; pembaruan layar lewat polling; PDF lewat dompdf; job pendek, idempoten, dan berpotongan.

## Alur kerja ringkas

1. Pilih ID dari `docs/STATUS.md` (Fase 1 berprioritas Must lebih dulu).
2. Baca baris PRD untuk ID itu dan aturan di `docs/rules/` yang relevan.
3. Tulis rencana singkat (tanpa tanggal atau estimasi). Bila ada ambiguitas atau konflik aturan, tanya dulu.
4. Tulis tes bersamaan atau lebih dulu (domain, fitur, otorisasi, komponen). Nama tes memuat ID, contoh `it('PAS-02 mencari pasien lewat nomor RM')`.
5. Implementasi per lapisan: Domain, Application, Infrastructure, Presentation, lalu frontend.
6. Jalankan `scripts/verify.sh` sampai hijau.
7. Centang ID di `docs/STATUS.md` dan tulis ringkasan perubahan.
8. Commit kecil: `feat(PAS-02): cari pasien lewat nomor RM`.

Rincian ada di `docs/rules/workflow.md`.

## Perintah penting

| Perintah | Fungsi |
| --- | --- |
| `scripts/verify.sh` | Gerbang penuh: format, analisis statis, Deptrac, guard, tes backend dan frontend |
| `scripts/guard.sh` | Pemeriksa aturan proyek (cepat, tanpa dependensi berat) |
| `scripts/make-context.sh <Nama>` | Membuat kerangka bounded context baru |
| `python3 scripts/status.py` | Ringkasan kemajuan kebutuhan dan Must yang tersisa |
| `/implement <ID>` | Slash command untuk mengerjakan satu kebutuhan sesuai alur |
| `/next` | Memilih kebutuhan berikutnya |
| `/review-rules` | Audit perubahan terhadap aturan (sub-agent `rules-reviewer`, `security-reviewer`) |
| `/bootstrap` | Mengerjakan Fase 0 sesuai `docs/BOOTSTRAP.md` |
| `/new-context <Nama>` | Membuat bounded context lewat skrip |
| `/adr <judul>` | Menulis ADR baru |
| `python3 scripts/selftest.py` | Menguji bahwa guard dan hook masih menangkap pelanggaran |

## File terlindungi

`CLAUDE.md`, `docs/rules/`, `docs/PRD/`, `docs/operations.md`, `docs/BOOTSTRAP.md`, `compose.yaml`, `tests-templates/`, `.claude/`, `scripts/`, `tooling/`, dan `.github/` tidak diedit oleh Claude Code. Hook menolak penyuntingannya, dan `scripts/guard.py` membandingkannya dengan segel checksum (`tooling/protected.sha256`). Bila perlu perubahan, tulis usulan di `docs/OPEN-QUESTIONS.md` dan minta pengguna mengubahnya. Dependensi baru hanya masuk lewat `tooling/approved-dependencies.json` yang diperbarui pengguna.

## Bila ragu

Berhenti dan tanya. Lebih baik satu pertanyaan singkat daripada pelanggaran aturan yang harus dibongkar.

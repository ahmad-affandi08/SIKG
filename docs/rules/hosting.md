# Aturan hosting: satu kode, dua profil

SIKG harus berjalan di dua profil hosting dengan **kode yang sama**; yang berbeda hanya konfigurasi dan prosedur rilis. Keputusan dan alasannya ada di `docs/adr/0006-dua-profil-hosting.md`. Prosedur rilis, cron, dan cadangan ada di `docs/operations.md`.

| | Profil A: server | Profil B: shared hosting |
| --- | --- | --- |
| Bentuk | VPS atau server cloud, akses root | Paket hosting bersama (cPanel, DirectAdmin, atau sejenis), tanpa root |
| Web | Nginx + PHP-FPM | Apache atau LiteSpeed + PHP versi pilihan dari panel |
| Antrean | `queue:work` di bawah Supervisor | `queue:work --stop-when-empty` dipanggil cron tiap menit |
| Scheduler | cron `schedule:run` | cron `schedule:run` (sama) |
| Cache, sesi, antrean | `database` atau Redis | `database` (atau `file` untuk cache) |
| Build aset | CI | CI (server tidak punya Node) |
| Rilis | Folder rilis + symlink `current` | Unggah paket rilis ke folder aplikasi (tanpa symlink) |
| Cadangan DB | Dump harian + binlog | Dump berkala lewat cron hosting, dienkripsi, dikirim keluar |
| Pemantauan | Pulse (opsional), Sentry, uptime | Sentry, `/health`, uptime eksternal |

Profil A tetap rekomendasi bila anggaran dan keahlian operasional ada. Profil B diterima untuk skala asumsi A1 (1-3 dokter, satu lokasi) dengan syarat di bagian 2 dan 6.

## 1. Aturan kode (berlaku untuk semua kode, wajib; CLAUDE.md M21)

1. **Driver bawaan `database`.** Fitur harus benar dengan `CACHE_STORE=database`, `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`. Redis hanya pilihan konfigurasi pada Profil A. Dilarang memanggil Redis langsung (`Redis::`, `Illuminate\Support\Facades\Redis`, `Predis`); dilarang bergantung pada Horizon.
2. **Konkurensi lewat MySQL.** Kunci jadwal, nomor urut, pembayaran, dan stok memakai kunci baris dalam transaksi (`docs/rules/database.md`), bukan kunci Redis dan bukan kunci berkas.
3. **Tanpa proses panjang.** Dilarang bergantung pada Supervisor, Horizon, Octane, Reverb, WebSocket, atau `queue:listen` sebagai daemon. Pekerjaan latar harus aman dijalankan oleh `queue:work --stop-when-empty --max-time=50` yang dipanggil cron.
4. **Job pendek dan idempoten.** Satu job selesai dalam batas waktu eksekusi hosting (anggap 60 detik). Pekerjaan lebih besar (impor PAS-08, ekspor Excel, laporan berat) dipecah per potongan yang ukurannya diatur konfigurasi, mencatat kemajuan di `import_batches` atau tabel laporan, dan dapat dilanjutkan setelah gagal. Setiap job menetapkan `tries` dan `backoff`. Job dijalankan dua kali tidak boleh menggandakan data.
5. **Scheduler tahan terlewat.** Semua tugas terjadwal didaftarkan di scheduler dan dijalankan oleh **satu** cron `schedule:run`. Tugas tidak boleh bergantung pada ketepatan menit atau jam; bila cron terlambat atau terlewat, tugas berikutnya menyusul tanpa data hilang atau ganda.
6. **Tanpa waktu nyata.** Pembaruan layar lewat polling TanStack Query (ADR-0002). Dilarang WebSocket, SSE, Pusher, dan Reverb. Endpoint polling ringan: satu query terindeks, respons kecil, mendukung `ETag` atau 304 bila mungkin. Polling hanya saat tab aktif (`docs/rules/frontend.md`).
7. **Tanpa eksekusi perintah sistem.** Kode aplikasi dan `src/` dilarang memakai `exec`, `shell_exec`, `system`, `passthru`, `proc_open`, `popen`, backtick, atau `Symfony\Component\Process`. Akibat langsung:
   - PDF memakai `barryvdh/laravel-dompdf` (PHP murni). `spatie/laravel-pdf`, Browsershot, wkhtmltopdf, dan Chromium **tidak** dipakai.
   - Excel memakai `maatwebsite/excel` dengan pembacaan dan penulisan berpotongan (chunk) dan antrean; jangan memuat seluruh berkas ke memori.
   - Gambar diolah dengan GD (ekstensi umum di hosting); Imagick tidak diwajibkan.
8. **Tanpa Node di server.** Server produksi tidak menjalankan `npm`, `node`, atau Vite. Aset dibangun di CI; paket rilis memuat `public/build` dan `vendor/` (`composer install --no-dev --optimize-autoloader`).
9. **Tanpa symlink.** Jangan memakai `php artisan storage:link` atau symlink apa pun. Berkas klinis ada di `storage/app/private` dan disajikan controller lewat URL bertanda tangan dengan streaming (bukan memuat seluruh berkas ke memori). Aset publik non-PHI (logo klinik) disajikan lewat route atau disalin ke `public/` saat pembuatan paket rilis.
10. **Tulis hanya di `storage/` dan `bootstrap/cache/`.** Dilarang menulis ke folder lain, mengandalkan hak root, mengubah `php.ini`, atau mengandalkan `ini_set` untuk batas kritis. Anggap `open_basedir` mungkin aktif.
11. **Tanpa fitur khusus MySQL tingkat server.** Dilarang trigger, stored procedure/function, event MySQL, dan `GRANT` per tabel (hak `SUPER` dan `TRIGGER` tidak tersedia di hosting bersama). Aturan seperti "audit_logs hanya INSERT" ditegakkan di aplikasi dan diuji (`tests-templates/Feature/AuditLogTest.php`), bukan oleh database. Migrasi hanya memakai hak `SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES, LOCK TABLES`.
12. **Konfigurasi hanya lewat `.env` dan `config/`.** `env()` hanya dipanggil dari `config/*.php` agar `config:cache` benar. Satu variabel `SIKG_HOSTING_PROFILE` (`server` atau `shared`) boleh menentukan *default* operasional (ukuran chunk, interval pemeriksaan, apakah berkas dienkripsi di aplikasi, pemeriksaan di SYS-06). Variabel itu tidak boleh mengubah aturan domain.
13. **Aman terhadap cron yang lambat.** Bila cron hosting minimal 5 menit, hasil laporan berat dan pengingat tertunda sampai 5 menit; layar harus menampilkan status "menunggu" dengan jujur, bukan menganggap gagal.
14. **Log tanpa PHI** (M11), channel `daily` dengan retensi 90 hari (`LOG_DAILY_DAYS=90`); pantau ukuran `storage/logs` karena kuota disk hosting bersama terbatas.
15. **Tanpa CDN eksternal** (sudah ada di `docs/rules/security-privacy.md`); pada hosting bersama hal ini juga mengurangi permukaan serangan.

## 2. Syarat penyedia untuk Profil B (go/no-go sebelum membeli atau pindah)

Diperiksa lewat halaman SYS-06 dan daftar ini; satu syarat wajib yang gagal berarti penyedia ditolak.

**Wajib**

| Syarat | Alasan |
| --- | --- |
| PHP 8.3 atau lebih baru dapat dipilih per domain dari panel | Laravel 13 (PRD bagian 9) |
| Ekstensi: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `intl`, `json`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `xmlwriter`, `simplexml`, `zip` | Laravel, dompdf, PhpSpreadsheet |
| MySQL 8.0 atau lebih baru, InnoDB, `utf8mb4`; pengguna database dengan hak di butir 11 | PRD bagian 9. MariaDB belum diverifikasi (Q-2 di `docs/OPEN-QUESTIONS.md`) |
| Cron dari panel, interval satu menit (diterima sampai lima menit, lihat butir 13) | Scheduler dan antrean |
| Dokumen akar (document root) domain dapat diarahkan ke folder `public/` | `.env`, `vendor/`, dan `storage/` tidak boleh terjangkau web. Bila tidak bisa, pakai pola cadangan di `docs/operations.md` bagian 2.3 |
| `.htaccess` berfungsi (Apache atau LiteSpeed) | Penulisan ulang URL dan penolakan berkas sensitif |
| HTTPS gratis atau berbayar dengan pembaruan otomatis | PRD bagian 14 |
| Pusat data di dalam negeri, kontrak tertulis tentang lokasi data | NFR-15 |
| `upload_max_filesize` minimal 12 MB dan `post_max_size` minimal 16 MB | RM-08 (10 MB per berkas) |
| `memory_limit` minimal 256 MB | Impor dan ekspor Excel, dompdf |
| `max_execution_time` minimal 60 detik untuk web; cron tidak dibatasi atau minimal 60 detik | Butir 4 |
| Fungsi PHP yang dipakai Laravel dan paket (di luar daftar butir 7) tidak dinonaktifkan | `proc_open`, `exec`, dan `symlink` boleh nonaktif karena tidak dipakai; fungsi lain dicek SYS-06 |

**Sangat dianjurkan**

- Akses SSH atau Terminal di panel (untuk `php artisan migrate --force` saat rilis). Tanpa itu, migrasi dijalankan lewat entri cron sekali jalan (prosedur di `docs/operations.md`). **Tidak ada route web untuk menjalankan migrasi atau perintah Artisan.**
- Cadangan penyedia (bukan pengganti cadangan sendiri), isolasi akun (CageFS atau sejenis), dan penyimpanan SSD atau NVMe.
- Batas proses PHP bersamaan (entry processes) dan batas inode dicatat; uji beban dilakukan pada hosting sasaran.

## 3. Tes dan penegakan

- **Tes kompatibilitas hosting (CI):** satu job menjalankan seluruh suite fitur dengan `CACHE_STORE=database`, `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`, **tanpa layanan Redis**, dan PHP dijalankan dengan `disable_functions=exec,shell_exec,system,passthru,proc_open,popen`. Suite harus lulus tanpa pengecualian.
- **Arch test:** tidak ada pemanggilan fungsi di butir 7 dan tidak ada `Redis` facade di `src/` dan `app/` (`tests/Architecture/`).
- **`scripts/guard.py` aturan `HOST`** (usulan; `scripts/` terlindungi, ditambahkan pengguna): menandai pelanggaran butir 1, 7, 9, dan 11 pada diff.
- **SYS-06 dan SYS-07** (PRD bagian 6.10) memberi tahu admin bila lingkungan tidak memenuhi syarat atau cron/antrean mati.
- Setiap kebutuhan yang menambah job, tugas terjadwal, berkas, PDF, atau Excel menyebut dalam rencana bagaimana ia memenuhi butir 3-10.

## 4. Batas yang diterima pada Profil B

Dicatat agar pemilik menyetujui dengan sadar; jawaban ada di `docs/OPEN-QUESTIONS.md` (Q-1, Q-3).

- **RPO lebih longgar.** Tanpa binlog, kehilangan data maksimal sebesar interval dump (usulan maksimal 6 jam) dan bukan 1 jam (NFR-03).
- **Ketersediaan** mengikuti SLA penyedia; 99,5% pada jam praktik (NFR-02) tidak dijamin oleh kami.
- **Performa** (NFR-01) bergantung pada beban pengguna lain di server yang sama; wajib diuji pada hosting sasaran sebelum go-live. Bila tidak memenuhi, pindah ke Profil A tanpa mengubah kode (`docs/operations.md` bagian 2.5).
- **Enkripsi disk** tidak bisa diatur pengguna. Mitigasi: berkas klinis dienkripsi di aplikasi (`docs/rules/security-privacy.md` bagian 4), kolom sensitif sudah terenkripsi, cadangan dienkripsi sebelum meninggalkan server.
- Laporan berat dan pengingat tertunda hingga satu interval cron.

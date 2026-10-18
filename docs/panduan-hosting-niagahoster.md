# Memasang SIKG di Niagahoster (shared hosting, Profil B)

Panduan ini mengikuti `docs/rules/hosting.md` dan `docs/operations.md` bagian 2.2 sampai 2.4. Nama menu panel bisa berbeda antarpaket dan antarversi panel; bila berbeda, cari menu yang fungsinya sama.

**Yang belum terverifikasi:** dari sisi kami, rincian paket Niagahoster (versi PHP yang tersedia, MySQL atau MariaDB, akses SSH, batas cron, pengubahan document root) belum dapat dipastikan karena bergantung pada paket dan akun Anda. Selesaikan bagian 1 lebih dulu sebelum membeli atau memasang apa pun.

## 1. Pastikan paket Niagahoster memenuhi syarat (go/no-go)

Tanyakan ke layanan pelanggan Niagahoster atau periksa di panel paket yang dipilih. Satu jawaban "tidak" pada butir wajib berarti paket itu tidak cocok.

| # | Pertanyaan | Wajib? | Bila "tidak" |
| --- | --- | --- | --- |
| 1 | Apakah PHP 8.3 atau lebih baru dapat dipilih untuk domain saya? | Wajib | Tidak bisa dipakai (Laravel 13) |
| 2 | Basis datanya MySQL 8.0+ atau MariaDB? Versi berapa? | Wajib MySQL 8.0+ | MariaDB belum diuji dan belum didukung (Q-2). Pilih Cloud VPS (bagian 9) atau penyedia lain |
| 3 | Apakah ada cron job dari panel, dan interval terkecilnya berapa? | Wajib | Interval sampai 5 menit diterima; lebih dari itu tidak cocok |
| 4 | Apakah document root domain bisa diarahkan ke subfolder `public`? | Dianjurkan | Pakai paket rilis varian `public_html` (bagian 3) |
| 5 | Apakah ada akses SSH atau Terminal di panel? | Dianjurkan | Migrasi dan penyiapan awal lewat cron sekali jalan (bagian 6) |
| 6 | Ekstensi PHP: bcmath, ctype, curl, dom, fileinfo, gd, intl, mbstring, openssl, pdo_mysql, tokenizer, xml, xmlwriter, simplexml, zip aktif? | Wajib | Minta diaktifkan |
| 7 | `memory_limit` minimal 256 MB, `upload_max_filesize` minimal 12 MB, `post_max_size` minimal 16 MB, `max_execution_time` minimal 60 detik? | Wajib | Minta dinaikkan atau atur lewat menu opsi PHP |
| 8 | Pusat data berada di Indonesia dan ada SSL gratis dengan pembaruan otomatis? | Wajib | Tidak cocok untuk data pasien |
| 9 | Fungsi PHP `proc_open`, `exec`, `symlink` boleh nonaktif? | Tidak masalah | SIKG tidak memakainya |

Contoh pesan ke layanan pelanggan:

> Saya akan memasang aplikasi Laravel 13. Mohon konfirmasi untuk paket [nama paket]: (1) apakah PHP 8.3 atau lebih baru dapat dipilih, (2) database-nya MySQL 8 atau MariaDB, (3) apakah ada cron job dan interval terkecilnya, (4) apakah document root domain dapat diarahkan ke folder `public` dan apakah ada akses SSH atau Terminal, (5) nilai `memory_limit`, `upload_max_filesize`, `post_max_size`, dan `max_execution_time`, serta apakah ekstensi PHP `intl`, `gd`, `zip`, `bcmath` aktif.

## 2. Siapkan domain, PHP, dan database

1. Arahkan domain atau subdomain ke hosting, lalu aktifkan SSL dan paksa HTTPS.
2. Di pengaturan PHP domain, pilih PHP 8.3 atau lebih baru dan aktifkan ekstensi dari tabel di atas.
3. Buat database dan satu pengguna database dengan hak penuh pada database itu saja. Catat nama database, pengguna, kata sandi, dan host (sering `localhost`).

## 3. Siapkan paket rilis

Server tidak menjalankan `composer` maupun `npm`, jadi paket dibuat di luar server.

**Cara utama (dari CI):** setelah CI hijau di cabang `main`, buka tab Actions di GitHub, buka eksekusi terbaru, lalu unduh artefak `sikg-rilis-shared-hosting`. Isinya dua arsip:

- `sikg-rilis.zip`: dipakai bila document root dapat diarahkan ke `app/public` (disarankan).
- `sikg-rilis-public_html.zip`: dipakai bila document root harus tetap `public_html`; berisi folder `sikg/` (aplikasi) dan `public_html/` (berkas publik).

**Cara cadangan (dari laptop, bila CI belum tersedia):** di folder proyek jalankan `composer install --no-dev --optimize-autoloader`, lalu `npm ci` dan `npm run build`. Kompres folder proyek **tanpa** `.env`, `.git`, `node_modules`, `tests`, `tests-templates`, `tooling`, `scripts`, `docs`, `storage/logs/*`, `storage/app/*`, dan `storage/framework/*` (kecuali foldernya). Pastikan `public/build` dan `vendor/` ikut. Untuk varian `public_html`, ikuti polanya di `docs/operations.md` bagian 2.3.

## 4. Unggah dan susun folder

Gunakan File Manager panel atau SFTP/FTP.

**Varian A (document root dapat diarahkan):**

1. Unggah `sikg-rilis.zip` ke folder di luar `public_html`, misalnya `/home/pengguna/`, lalu ekstrak. Hasilnya `/home/pengguna/app/`.
2. Di pengaturan domain, ubah document root menjadi `/home/pengguna/app/public`.

**Varian B (document root tetap `public_html`):**

1. Unggah dan ekstrak `sikg-rilis-public_html.zip` di `/home/pengguna/`. Hasilnya `/home/pengguna/sikg/` dan isi `public_html/` yang timpa/gabung ke `public_html` domain Anda.
2. Pastikan `sikg/` **bukan** di dalam `public_html`.

Pastikan folder `storage/` dan `bootstrap/cache/` dapat ditulis (umumnya izin 755 sudah cukup; naikkan ke 775 bila ada galat izin).

## 5. Berkas konfigurasi `.env`

Buat berkas `.env` di folder aplikasi (`app/` atau `sikg/`) langsung di File Manager, atau salin dari `.env.example` lalu ubah. Jangan pernah mengunggahnya lewat repositori. Isi minimal:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-klinik-anda
APP_KEY=              # lihat di bawah
PII_HASH_KEY=         # teks acak panjang; lihat di bawah

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
SIKG_HOSTING_PROFILE=shared
LOG_DAILY_DAYS=90
SIKG_BACKUP_INTERVAL_HOURS=6
SIKG_ALERT_EMAIL=email-admin@contoh.id
HEALTH_TOKEN=         # teks acak untuk pemeriksaan /health
BACKUP_KEY=           # teks acak minimal 16 karakter, simpan terpisah dari server
MAIL_*                # isi dengan SMTP dari panel email hosting
```

Bangkitkan nilai acak di laptop Anda, bukan di server bersama: `php artisan key:generate --show` memberi `APP_KEY`; untuk `PII_HASH_KEY`, `HEALTH_TOKEN`, dan `BACKUP_KEY` pakai pembuat kata sandi acak yang panjang (minimal 32 karakter). **Simpan `APP_KEY`, `PII_HASH_KEY`, dan `BACKUP_KEY` di pengelola kata sandi, terpisah dari server.** Tanpa `APP_KEY` dan `PII_HASH_KEY` yang sama, NIK dan nomor HP tidak terbaca lagi setelah pemulihan, dan `PII_HASH_KEY` tidak boleh diganti setelah ada data. Lengkapi variabel lain dari `.env.example`.

## 6. Migrasi dan penyiapan awal

**Bila ada SSH atau Terminal** (di folder aplikasi):

```
php artisan migrate --force
php artisan sikg:install --clinic="Nama Klinik" --name="Nama Pemilik" --username=pemilik --email=email@contoh.id
php artisan db:seed --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan sikg:preflight
```

`sikg:install` meminta kata sandi sementara (minimal 12 karakter) bila `--password` tidak diberikan; Pemilik wajib menggantinya saat masuk pertama. Perintah ini hanya jalan bila belum ada klinik, dan **tidak** menjalankan data contoh.

`db:seed --force` mengisi peran dan izin serta **data awal klinik** (seeder `StarterDataSeeder`), tanpa data pasien: lima metode pembayaran, sembilan kategori biaya operasional, katalog tindakan gigi umum dan katalog obat dan bahan umum, serta hari libur nasional bertanggal tetap untuk tahun berjalan dan tahun depan. Katalog tindakan dan obat dibuat **nonaktif dengan harga Rp 0**: klinik mengisi tarif dan harga lewat Pengaturan, Data master (Ubah), lalu mengaktifkannya. Kode ICD-9-CM dan kelas obat adalah usulan dan wajib diperiksa dokter atau petugas rekam medis klinik. Libur yang berpindah tiap tahun (Idulfitri, Imlek, Nyepi, dan lainnya) ditambahkan klinik sendiri. Seeder ini aman dijalankan ulang (misalnya awal tahun berikutnya untuk menambah libur tetap) dan tidak menimpa data yang sudah diubah klinik.

**Bila tanpa SSH:** buat entri cron sekali jalan satu per satu di panel (jalur PHP dan folder sesuaikan, lihat bagian 7), tunggu satu menit sampai selesai, lalu **hapus entri itu**:

```
cd /home/pengguna/app && /usr/local/bin/php artisan migrate --force > /home/pengguna/migrasi.log 2>&1
cd /home/pengguna/app && /usr/local/bin/php artisan sikg:install --clinic="Nama Klinik" --name="Nama Pemilik" --username=pemilik --email=email@contoh.id --password="KataSandiSementara123" > /home/pengguna/install.log 2>&1
cd /home/pengguna/app && /usr/local/bin/php artisan db:seed --force > /home/pengguna/seed.log 2>&1
cd /home/pengguna/app && /usr/local/bin/php artisan config:cache > /dev/null 2>&1
```

Karena kata sandi sementara tertulis di entri cron, hapus entri itu dan berkas `install.log`, lalu segera masuk dan ganti kata sandi. Tidak ada route web untuk migrasi atau perintah Artisan.

## 7. Cron

Buat dua entri, interval satu menit (atau lima menit bila itu batas panel). Pakai jalur biner PHP yang sama dengan versi yang dipilih untuk domain (lihat di panel; bentuk umum `/usr/local/bin/php` atau `/opt/alt/php83/usr/bin/php`).

```
* * * * * cd /home/pengguna/app && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /home/pengguna/app && /usr/local/bin/php artisan queue:work --stop-when-empty --max-time=50 --tries=3 >> /dev/null 2>&1
```

Jangan menjalankan `queue:listen` atau `queue:work` tanpa `--stop-when-empty`. Scheduler inilah yang menjalankan pengingat, rekonsiliasi pembayaran, cadangan, dan pemeriksaan kesehatan.

## 8. Pemeriksaan setelah terpasang

1. Buka `https://domain/login` dan masuk sebagai Pemilik, ganti kata sandi.
2. Buka Pengaturan, Sistem (Kesiapan Lingkungan, SYS-06): semua butir wajib harus lulus; cron dan antrean harus terbaca hidup setelah beberapa menit.
3. Pastikan `https://domain/.env` dan `https://domain/storage/logs/laravel.log` menghasilkan 403 atau 404. Bila terbuka, **hentikan** dan perbaiki document root atau gunakan varian B.
4. Daftarkan `https://domain/up` ke layanan pemantau eksternal (uptime) dan `/health` dengan token ke pemantau denyut. Cron yang mati tidak dapat memberi tahu dirinya sendiri.
5. Isi profil klinik, dokter, kursi, tarif, obat, metode pembayaran, dan pengguna lain.
6. Uji cetak PDF (kwitansi, resep) dan unggah satu foto 5 MB. Uji kecepatan dengan beberapa pengguna bersamaan sebelum go-live; bila lambat, naikkan paket atau pindah ke VPS tanpa mengubah kode.

## 9. Bila paket shared hosting tidak cocok

Bila PHP 8.3, MySQL 8, atau cron tidak tersedia, pilih Cloud VPS Niagahoster atau penyedia lain (Profil A di `docs/rules/hosting.md`). Pada VPS Anda mengelola sendiri Nginx, PHP-FPM 8.3, MySQL 8, dan Supervisor; kodenya sama.

## 10. Cadangan dan pembaruan

- Aplikasi membuat cadangan terenkripsi sendiri (`php artisan sikg:backup`, dijalankan scheduler) ke `storage/app/backups`. **Itu masih di server yang sama.** Wajib disalin berkala ke tempat lain di Indonesia (unduh lewat SFTP, atau cron ke penyimpanan lain); mekanisme tetapnya masih keputusan terbuka (Q-3, Q-22). Lihat `docs/runbook-cadangan.md`.
- Pembaruan: cadangkan database dan folder aplikasi, unggah paket rilis baru ke folder baru, salin `.env` dan `storage/app`, tukar nama folder, jalankan `php artisan migrate --force` dan `config:cache`, lalu cek halaman Sistem. Ringkasan di `docs/operations.md` bagian 2.2.
- Fitur yang bergantung akun pihak ketiga (SATUSEHAT, WhatsApp API, DOKU) mati sampai kredensialnya diisi di Pengaturan. Untuk DOKU ikuti `docs/kepatuhan/pembayaran-online-doku.md`; alamat notifikasinya memakai domain ini dan harus HTTPS.

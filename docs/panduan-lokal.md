# Menjalankan SIKG di komputer lokal

Untuk mencoba atau mengembangkan. Data contoh seluruhnya sintetis (M12); jangan memasukkan data pasien nyata ke lingkungan lokal.

## Prasyarat

- PHP 8.3 atau lebih baru dengan ekstensi: bcmath, ctype, curl, dom, fileinfo, gd, intl, json, mbstring, openssl, pdo_mysql, tokenizer, xml, xmlwriter, simplexml, zip
- Composer 2
- Node.js 20 atau lebih baru dan npm
- MySQL 8: lewat Docker (`docker compose up -d mysql`, memakai `compose.yaml`) atau MySQL yang sudah terpasang

## Langkah

1. Salin konfigurasi dan buat kunci aplikasi: `cp .env.example .env` lalu `php artisan key:generate`.
2. Buka berkas konfigurasi itu di editor Anda sendiri dan isi blok `DB_*` sesuai MySQL lokal. Bila memakai `compose.yaml`: basis data `sikg`, pengguna `sikg`, kata sandi `sikg_local_only`, host `127.0.0.1`. Isi juga `PII_HASH_KEY` dengan teks acak yang panjang (kunci untuk pencarian NIK dan nomor HP; jangan diganti setelah ada data).
3. Pasang pustaka dan bangun tampilan: `composer install`, lalu `npm install --ignore-scripts`, lalu `npm run build`.
4. Buat tabel: `php artisan migrate`.
5. Isi data contoh (hanya di lokal): `php artisan db:seed --class=DemoSeeder` (klinik, pengguna tiap peran, dokter, kursi, tarif, obat, dan pasien sintetis). Untuk data awal saja tanpa pasien dan pengguna contoh, setelah ada klinik: `php artisan db:seed --class=StarterDataSeeder` (metode bayar, kategori biaya, katalog tindakan dan obat nonaktif berharga 0, dan libur nasional tetap). Kata sandi acak untuk semua pengguna dicetak sekali di layar; catat. Pengguna contoh: `pemilik`, `admin`, `rina` (resepsionis), `sari` (dokter), `nia` (perawat), `kasir`.
6. Jalankan: `php artisan serve`, lalu buka http://127.0.0.1:8000 dan masuk.

`DemoSeeder` menolak berjalan bila `APP_ENV` bernilai `production` dan bila sudah ada klinik di basis data.

## Hal yang berguna

- Cron (pengingat, antrean, rekonsiliasi pembayaran) tidak berjalan sendiri di lokal. Untuk mencoba: `php artisan schedule:work` di terminal lain, dan `php artisan queue:work --stop-when-empty` bila ada pekerjaan antrean.
- Fitur yang bergantung akun pihak ketiga (SATUSEHAT, WhatsApp API, DOKU) mati sampai kredensialnya diisi di Pengaturan. Untuk DOKU ikuti `docs/kepatuhan/pembayaran-online-doku.md`; alamat notifikasi harus HTTPS dan dapat dijangkau internet, jadi uji pembayaran sungguhan butuh terowongan seperti ngrok atau Cloudflare Tunnel.
- Mengubah kode tampilan: jalankan `npm run dev` di terminal lain (Vite) sambil `php artisan serve`; kembali ke `npm run build` bila ingin hasil seperti server sungguhan.
- Menjalankan tes: `composer test` (Pest) dan `npm test` (Vitest). Gerbang penuh: `scripts/verify.sh` (perlu bash; di Windows pakai WSL atau Git Bash).
- Tes E2E (Playwright, peramban sungguhan): `npm run e2e`. Tes membuat ulang basis data uji (`sikg_test`, dapat diubah lewat `DB_*`) dan mengisinya dengan data sintetis `DemoSeeder` (kata sandi `sandi-uji-lokal`, jadwal dokter sehari penuh), lalu menjalankan alur utama dari pendaftaran pasien sampai tutup kas, booking online, impor stok awal, dan otorisasi per peran. Jangan arahkan ke basis data berisi data nyata: tes menghapus seluruh tabel. Bila peramban Playwright belum terpasang tetapi ada Chromium di sistem, isi `E2E_CHROMIUM` dengan jalur biner itu; `E2E_SKIP_DB=1` melewati pembuatan ulang basis data.
- Mengulang dari awal (hanya lokal): hapus dan buat ulang basis data `sikg`, lalu ulangi langkah 4 dan 5.
- Pengguna Windows: paling mudah memakai WSL 2 (Ubuntu) dan menjalankan semua perintah di dalamnya.

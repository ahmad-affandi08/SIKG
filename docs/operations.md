# Operasional: lingkungan, cadangan, rilis

## 1. Lingkungan

| Lingkungan | Tujuan | Data |
| --- | --- | --- |
| Lokal | Pengembangan | Sintetis |
| CI | Tes otomatis | Sintetis, MySQL layanan sementara |
| Staging | UAT dan latihan | Sintetis atau anonim |
| Produksi | Klinik | Nyata; hanya diakses lewat proses rilis |

- Server di dalam negeri. Versi minimal: PHP 8.3, MySQL 8. Node LTS hanya untuk CI dan lokal, tidak dipasang di produksi. Redis opsional (Profil A); driver `database` untuk antrean, cache, dan sesi adalah bawaan.
- Dua profil hosting didukung dengan kode yang sama (`docs/rules/hosting.md`, ADR-0006): **Profil A** server (VPS atau cloud) dan **Profil B** shared hosting. Perbedaannya hanya konfigurasi dan prosedur di bawah.
- Satu klinik, satu cabang pada awalnya; kolom `clinic_id` dan `branch_id` sudah ada untuk pertumbuhan.
- Konfigurasi lewat `.env`. `.env.example` selalu diperbarui bersama kode. Kunci aplikasi dan kunci blind index dibuat per lingkungan.

## 2. Rilis

Claude Code tidak melakukan rilis ke produksi atau mengubah konfigurasi produksi (M17). Rilis dilakukan manusia memakai daftar di bawah.

### 2.1 Profil A: server

1. Merge ke cabang utama hanya bila CI hijau.
2. Build aset (`npm run build`), `composer install --no-dev --optimize-autoloader`.
3. Mode pemeliharaan singkat bila ada migrasi yang butuh kunci.
4. `php artisan migrate --force`, lalu cache config dan route, lalu restart antrean.
5. Pemeriksaan kesehatan: halaman masuk, pencarian pasien, antrean.
6. Rollback: kembali ke rilis sebelumnya; migrasi memakai pola expand-contract sehingga rilis sebelumnya tetap berjalan.

### 2.2 Profil B: shared hosting

Paket rilis dibuat oleh CI (job `release-package`, artefak `sikg-rilis-shared-hosting`: `sikg-rilis.zip` untuk akar dokumen ke `public/`, dan `sikg-rilis-public_html.zip` untuk pola bagian 2.3), bukan di server: arsip berisi kode, `vendor/` (`--no-dev --optimize-autoloader`), dan `public/build`; tanpa `.env`, `node_modules`, `tests/`, dan `.git`. Server tidak menjalankan `npm` maupun `composer install`.

1. Merge ke cabang utama hanya bila CI hijau (termasuk job kompatibilitas hosting bersama); unduh arsip rilis dari CI.
2. Cadangkan database dan folder aplikasi saat ini (lihat bagian 3). Simpan salinan folder aplikasi lama sebagai `app-sebelumnya` bila kuota disk cukup.
3. Aktifkan mode pemeliharaan (`php artisan down`) lewat SSH/Terminal, atau lewat entri cron sekali jalan bila tanpa keduanya.
4. Unggah arsip (File Manager, SFTP, atau Git deploy dari panel) dan ekstrak ke folder aplikasi, **menimpa kode tetapi tidak `.env`, `storage/app`, dan `storage/logs`**. Lebih aman: ekstrak ke folder baru, salin `.env` dan `storage/app`, lalu tukar nama folder.
5. Migrasi: `php artisan migrate --force` lewat SSH/Terminal. Tanpa keduanya, buat satu entri cron sekali jalan untuk perintah itu, tunggu selesai, lalu hapus entri tersebut. Tidak ada route web untuk migrasi.
6. `php artisan config:cache && php artisan route:cache && php artisan view:cache` (atau entri cron sekali jalan). Hapus berkas pemeliharaan (`php artisan up`).
7. Pemeriksaan kesehatan: halaman masuk, pencarian pasien, antrean, dan **halaman Kesiapan Lingkungan (SYS-06) tanpa galat**.
8. Rollback: kembalikan folder `app-sebelumnya` dan, bila migrasi sudah jalan, andalkan pola expand-contract (rilis lama tetap berjalan pada skema baru); pemulihan database dari cadangan hanya bila migrasi merusak data.

### 2.3 Profil B: document root tidak dapat diarahkan ke `public/`

Hanya bila panel tidak mengizinkan. Letakkan seluruh aplikasi **satu tingkat di atas** `public_html` (mis. `~/sikg/`), dan isi `public/` ke `public_html/` (atau subfolder domain). Ubah jalur `require` di `public_html/index.php` agar menunjuk `../sikg/vendor/autoload.php` dan `../sikg/bootstrap/app.php`. Pastikan `.env`, `vendor/`, dan `storage/` **tidak** berada di bawah `public_html`, dan `.htaccess` menolak berkas berawalan titik. Perubahan `index.php` ini bagian dari pembuatan paket rilis (bukan diedit manual tiap rilis). Verifikasi dengan membuka `https://domain/.env` dan `https://domain/storage/logs/laravel.log`: keduanya harus 403 atau 404.

### 2.4 Cron (Profil B, dan Profil A tanpa Supervisor)

Dua entri cron dari panel, interval satu menit, dengan biner PHP 8.3 yang sama dengan yang dipilih untuk domain (cek `which php` atau jalur dari panel):

```
* * * * * cd /jalur/aplikasi && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /jalur/aplikasi && php artisan queue:work --stop-when-empty --max-time=50 --tries=3 >> /dev/null 2>&1
```

Bila panel membatasi interval minimum lima menit, pakai interval itu dan terima penundaan laporan berat dan pengingat (hosting.md butir 13). Jangan menjalankan `queue:listen` atau `queue:work` tanpa `--stop-when-empty` dari cron; proses akan menumpuk dan menghabiskan kuota proses.

### 2.5 Jalur naik: dari shared hosting ke server

Karena kode sama, perpindahan tidak mengubah kode domain.

1. Siapkan server (Profil A) dan staging di sana; pulihkan cadangan terbaru ke staging dan jalankan uji beban.
2. Atur `.env` server: Supervisor untuk `queue:work`, cron hanya `schedule:run`, Redis bila diinginkan (ubah `CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION`). Biarkan enkripsi berkas tingkat aplikasi tetap menyala agar berkas lama tetap terbaca.
3. Cutover di luar jam praktik: mode pemeliharaan di hosting lama, dump terakhir, pulihkan, salin `storage/app` dan `.env` kunci (APP_KEY dan kunci blind index **harus sama**, kalau tidak NIK dan HP tidak terbaca), ubah DNS, verifikasi, dan simpan hosting lama baca-saja sampai pemulihan terverifikasi.

## 3. Cadangan dan pemulihan

- Cadangan database penuh harian dan log biner berkelanjutan, disimpan terenkripsi di lokasi terpisah di dalam negeri. Berkas unggahan dicadangkan bersama.
- Retensi cadangan: harian 30 hari, bulanan 12 bulan, tahunan disimpan terus karena kewajiban retensi rekam medis 25 tahun (Permenkes 24/2022 Pasal 39 ayat 1).
- Target pemulihan: RPO maksimal 1 jam, RTO maksimal 4 jam (NFR-03). Cadangan penuh harian ditambah binlog MySQL.
- Uji pemulihan ke server uji dilakukan berkala dan sebelum go-live; hasil dicatat.

**Profil B (tanpa binlog dan tanpa root)**

- Dump database berkala lewat cron hosting (`mysqldump` dipanggil cron, **bukan** kode aplikasi), interval menentukan RPO (usulan maksimal 6 jam; keputusan pemilik, Q-1). Dump dikompres dan **dienkripsi sebelum meninggalkan server**, lalu dikirim ke penyimpanan di penyedia berbeda dan di dalam negeri (SFTP, rclone, atau penyimpanan objek) bila alat itu tersedia; mekanismenya diputuskan di Q-3.
- Cadangan folder `storage/app` (berkas klinis, sudah terenkripsi aplikasi) harian ke tujuan yang sama; `.env` dan kunci disimpan terpisah dan terenkripsi, tidak ikut dump.
- Cadangan bawaan penyedia hosting adalah tambahan, bukan pengganti. Simpan dump lokal hanya sementara agar kuota disk tidak habis; retensi mengikuti PRD bagian 17 di tujuan jarak jauh.
- Uji pemulihan sama kerasnya dengan Profil A: pulihkan dump ke database lain di hosting atau lokal dan buka aplikasi.

## 4. Pemantauan

- Sentry (dengan scrubbing PHI), Laravel Pulse atau setara (hanya Profil A; Pulse butuh proses yang selalu hidup), pemeriksaan ketersediaan, peringatan antrean macet dan ruang disk.
- Profil B: pantau `/up` dengan layanan uptime eksternal; pantau `/health` (dilindungi token, tanpa PHI) untuk umur denyut scheduler dan antrean (SYS-07). Layanan pemantau eksternal wajib, karena bila cron mati aplikasi tidak bisa mengirim peringatan sendiri. Peringatan ruang disk dan kuota inode diperiksa terjadwal.
- Log aplikasi tanpa PHI, rotasi harian, retensi 90 hari.

## 5. Migrasi data lama

- Impor dari Excel lewat `import_batches`: validasi per baris, pratinjau, dry run, laporan galat, dan rekonsiliasi jumlah.
- Dijalankan lebih dulu pada salinan. Data pasien lama dipetakan ke nomor RM yang ada bila sudah punya.
- Data sumber dilindungi sama seperti data produksi dan tidak boleh masuk repositori.

## 6. Pelatihan dan serah terima

- Panduan singkat per peran (resepsionis, dokter, perawat, kasir, admin, pemilik) di `docs/PRD/` bagian pelatihan dan di dalam aplikasi.
- Daftar kontak dukungan dan prosedur eskalasi diisi klinik.

## 7. Insiden pelindungan data

1. Isolasi: cabut sesi dan kunci akun terdampak.
2. Catat: apa, kapan, data apa, berapa pasien (dari `audit_logs` dan `access_logs`).
3. Perbaiki penyebab dan rotasi kunci yang mungkin bocor.
4. Pemberitahuan kepada pasien dan pihak berwenang sesuai UU 27/2022; keputusan dan wording oleh pemilik klinik dan penasihat hukum.
5. Tinjauan pasca-insiden dan perbaikan kontrol.

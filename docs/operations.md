# Operasional: lingkungan, cadangan, rilis

## 1. Lingkungan

| Lingkungan | Tujuan | Data |
| --- | --- | --- |
| Lokal | Pengembangan | Sintetis |
| CI | Tes otomatis | Sintetis, MySQL layanan sementara |
| Staging | UAT dan latihan | Sintetis atau anonim |
| Produksi | Klinik | Nyata; hanya diakses lewat proses rilis |

- Server di dalam negeri. Versi minimal: PHP 8.3, MySQL 8, Node LTS, Redis (opsional pada Fase 1; driver `database` untuk antrean dan cache dapat dipakai).
- Satu klinik, satu cabang pada awalnya; kolom `clinic_id` dan `branch_id` sudah ada untuk pertumbuhan.
- Konfigurasi lewat `.env`. `.env.example` selalu diperbarui bersama kode. Kunci aplikasi dan kunci blind index dibuat per lingkungan.

## 2. Rilis

1. Merge ke cabang utama hanya bila CI hijau.
2. Build aset (`npm run build`), `composer install --no-dev --optimize-autoloader`.
3. Mode pemeliharaan singkat bila ada migrasi yang butuh kunci.
4. `php artisan migrate --force`, lalu cache config dan route, lalu restart antrean.
5. Pemeriksaan kesehatan: halaman masuk, pencarian pasien, antrean.
6. Rollback: kembali ke rilis sebelumnya; migrasi memakai pola expand-contract sehingga rilis sebelumnya tetap berjalan.

Claude Code tidak melakukan rilis ke produksi atau mengubah konfigurasi produksi (M17). Rilis dilakukan manusia memakai daftar di atas.

## 3. Cadangan dan pemulihan

- Cadangan database penuh harian dan log biner berkelanjutan, disimpan terenkripsi di lokasi terpisah di dalam negeri. Berkas unggahan dicadangkan bersama.
- Retensi cadangan: harian 30 hari, bulanan 12 bulan, tahunan disimpan terus karena kewajiban retensi rekam medis 25 tahun (Permenkes 24/2022 Pasal 39 ayat 1).
- Target pemulihan: RPO maksimal 1 jam, RTO maksimal 4 jam (NFR-03). Cadangan penuh harian ditambah binlog MySQL.
- Uji pemulihan ke server uji dilakukan berkala dan sebelum go-live; hasil dicatat.

## 4. Pemantauan

- Sentry (dengan scrubbing PHI), Laravel Pulse atau setara, pemeriksaan ketersediaan, peringatan antrean macet dan ruang disk.
- Log aplikasi tanpa PHI, rotasi harian.

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

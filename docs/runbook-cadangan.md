# Runbook cadangan dan pemulihan (SYS-02)

Dokumen ini melengkapi `docs/operations.md` bagian 3. Kode aplikasi tidak memakai `mysqldump`, `exec`, atau proses sistem (M21), sehingga cadangan berjalan sama di server dan shared hosting.

## Yang dikerjakan aplikasi

| Tugas | Perintah | Jadwal (scheduler) | Hasil |
| --- | --- | --- | --- |
| Cadangan data | `php artisan sikg:backup` | Dicek tiap 30 menit; hanya berjalan bila sudah jatuh tempo menurut interval | Berkas `sikg-YYYYMMDD-HHMMSS.sikgbak` di disk cadangan, terenkripsi |
| Salinan berkas unggahan | bagian dari `sikg:backup` | Sama | Cermin `files/...` di disk cadangan; hanya berkas baru atau berubah yang disalin |
| Uji pemulihan | `php artisan sikg:backup:verify [berkas]` | Mingguan (Minggu 03:30) | Memulihkan ke tabel sementara, membandingkan jumlah baris dan checksum tiap tabel |
| Pemangkasan retensi | `php artisan sikg:backup:prune` | Harian 04:30 | Harian 30 hari, bulanan 12 bulan, tahunan disimpan terus |
| Pemulihan bencana | `php artisan sikg:backup:restore <berkas> --confirm` | Manual | Memulihkan ke basis data baru yang kosong |

Karena cadangan hanya berjalan bila jatuh tempo, cron yang terlewat atau terlambat tidak menumpuk cadangan maupun membuatnya hilang. Hasil setiap jalan tercatat di tabel `backup_runs` dan tampil di halaman Pengaturan, Sistem. Bila cadangan gagal, terlambat lebih dari dua kali interval, atau uji pemulihan gagal, `sikg:health-check` mengirim surel ke `SIKG_ALERT_EMAIL` (sekali per perubahan keadaan).

## Konfigurasi

| Variabel | Arti | Bawaan |
| --- | --- | --- |
| `SIKG_BACKUP_INTERVAL_HOURS` | Interval cadangan dalam jam. RPO sama dengan interval; usulan 6 pada shared hosting tanpa binlog, 24 pada server | 24 |
| `SIKG_BACKUP_DISK` | Nama disk di `config/filesystems.php` tempat cadangan ditulis | `backup` (`storage/app/backups`) |
| `BACKUP_KEY` | Kunci enkripsi cadangan (minimal 16 karakter, atau `base64:`). Simpan terpisah dari server | Diturunkan dari `APP_KEY` |

Cadangan hanya berisi data; skema berasal dari migrasi. Tabel volatil (sesi, cache, antrean, denyut, catatan cadangan) tidak ikut.

## Mengirim cadangan keluar server

Disk bawaan berada di server yang sama, jadi itu baru tahap pertama. Salinan ke penyedia dan lokasi berbeda di dalam negeri dilakukan di luar kode aplikasi (keputusan mekanisme di Q-3 dan Q-22):

- Profil B (shared hosting): entri cron hosting yang menyalin isi `storage/app/backups/` (berkas `.sikgbak` dan folder `files/`) ke tujuan jarak jauh dengan alat penyedia (SFTP, rclone, atau penyimpanan objek), lalu mengosongkan salinan lokal lama bila kuota disk terbatas. Berkas `.sikgbak` sudah terenkripsi dan berkas unggahan sudah terenkripsi aplikasi.
- Profil A (server): sinkronisasi harian dengan alat server ke penyimpanan objek di penyedia berbeda, ditambah binlog MySQL untuk pemulihan ke titik waktu.
- `.env`, `APP_KEY`, `PII_HASH_KEY`, dan `BACKUP_KEY` disimpan terpisah dan terenkripsi, tidak ikut cadangan. Tanpa `APP_KEY` dan `PII_HASH_KEY` yang sama, NIK dan nomor HP tidak terbaca setelah pemulihan.

## Pemulihan bencana

1. Siapkan server atau hosting baru dan salin kode rilis, `.env` (kunci yang sama), serta berkas `.sikgbak` dan folder `files/` dari tujuan jarak jauh.
2. Buat basis data baru yang kosong, lalu `php artisan migrate --force` sampai versi yang sama dengan cadangan. Jangan menjalankan seeder.
3. Letakkan berkas cadangan di disk cadangan, lalu `php artisan sikg:backup:restore sikg-YYYYMMDD-HHMMSS.sikgbak --confirm`. Perintah menolak basis data yang berisi data atau skema yang berbeda, dan tidak pernah menghapus atau menimpa data.
4. Salin folder `files/` kembali ke `storage/app/private/`.
5. Buka aplikasi, periksa halaman Sistem, jumlah pasien, dan satu rekam medis. Catat hasil pemulihan sebagai uji pemulihan.

Tabel rujukan ICD-10 (`diagnoses`) diisi oleh migrasi dengan ID acak; pemulihan menyamakan ID-nya berdasarkan kode ICD-10 sehingga rujukan dari rekam medis tetap benar.

## Uji pemulihan

`sikg:backup:verify` memerlukan hak membuat tabel sementara (`CREATE TEMPORARY TABLES`), yang umumnya ada pada shared hosting. Uji ini tidak menyentuh data aplikasi. Uji bergantung pada skema yang sama dengan cadangan; setelah rilis yang mengubah kolom, buat cadangan baru lebih dulu lalu uji.

Uji pemulihan ke server atau basis data terpisah (seperti prosedur bencana di atas) tetap dilakukan berkala dan sebelum go-live, dengan hasil dicatat (PRD 12: "Uji pemulihan cadangan berhasil dan terdokumentasi").

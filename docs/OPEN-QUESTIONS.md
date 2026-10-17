# Pertanyaan terbuka dan konflik

Claude Code menambahkan entri di sini bila menemukan kebutuhan ambigu, konflik antar dokumen, atau keputusan yang bukan wewenangnya. Pengguna menjawab, lalu entri dipindah ke bagian "Terjawab" beserta keputusannya. Jangan menulis tanggal atau estimasi.

Format entri:

```
### Q-<nomor>: <judul singkat>
- Kebutuhan terkait: <ID>
- Dokumen yang bertabrakan atau kurang: <path dan butir>
- Opsi: (a) ... akibatnya ...; (b) ... akibatnya ...
- Rekomendasi:
```

## Terbuka

Pertanyaan bisnis untuk pemilik klinik ada di `docs/PRD/09-risiko-pertanyaan-terbuka.md` (15 butir). Sampai dijawab, pakai asumsi A1 sampai A8 di `docs/PRD/00-ringkasan-lingkup-persona.md`, kecuali pertanyaan itu menentukan struktur data (misalnya skema jasa dokter, butir 8): berhenti dan tanya.

### Q-1: RPO pada shared hosting (tanpa binlog)
- Kebutuhan terkait: NFR-03, SYS-02
- Dokumen yang bertabrakan atau kurang: `docs/PRD/04-non-fungsional.md` NFR-03 (RPO 1 jam) tidak dapat dipenuhi tanpa binlog; `docs/rules/hosting.md` bagian 4
- Opsi: (a) terima RPO sebesar interval dump, usulan maksimal 6 jam, dump lebih sering di jam praktik, akibatnya sampai 6 jam data dapat hilang bila server rusak; (b) wajibkan Profil A untuk klinik yang butuh RPO 1 jam, akibatnya biaya dan keahlian operasional lebih tinggi
- Rekomendasi: (a) untuk klinik 1-3 dokter dengan formulir cetak sebagai cadangan (R6), keputusan pemilik klinik

### Q-2: Dukungan MariaDB
- Kebutuhan terkait: NFR-05, `docs/rules/database.md`
- Dokumen yang bertabrakan atau kurang: banyak shared hosting menyediakan MariaDB, sedangkan stack menetapkan MySQL 8 (M1, M3)
- Opsi: (a) hanya MySQL 8.0+ (penyedia dipilih sesuai), akibatnya pilihan penyedia lebih sempit; (b) uji dan dukung MariaDB versi tertentu di CI, akibatnya perubahan stack yang perlu ADR dan tes ganda (JSON, FULLTEXT, kunci baris, `utf8mb4` collation)
- Rekomendasi: (a) bawaan; (b) hanya bila tidak ada penyedia dalam negeri yang menyediakan MySQL 8

### Q-3: Mekanisme cadangan di Profil B
- Kebutuhan terkait: SYS-02, `docs/operations.md` bagian 3
- Dokumen yang bertabrakan atau kurang: M17 dan `scripts/` terlindungi; M21 melarang `exec` di kode aplikasi, sedangkan dump butuh `mysqldump` dan pengiriman keluar server butuh SFTP atau rclone
- Opsi: (a) skrip shell operasional di `scripts/ops/` yang dipanggil cron panel (mysqldump, enkripsi, kirim), ditulis dan disetujui pengguna; (b) paket `spatie/laravel-backup` (butuh persetujuan dependensi; memakai `Process` di dalam paket dan biner `mysqldump`, bertentangan dengan semangat M21 dan mungkin tidak jalan di hosting); (c) fitur cadangan penyedia hosting saja, akibatnya tidak terenkripsi sendiri dan tidak terpisah penyedia
- Rekomendasi: (a)

### Q-4: Pemantau eksternal untuk `/health`
- Kebutuhan terkait: SYS-07, NFR-13
- Dokumen yang bertabrakan atau kurang: aplikasi tidak bisa memberi tahu bahwa cron-nya sendiri mati; butuh layanan eksternal (uptime/dead-man's-switch) yang mengakses `/up` atau `/health`
- Opsi: (a) layanan uptime pihak ketiga dengan header token; (b) layanan yang hanya mendukung token di URL, dengan token khusus-baca yang hanya menampilkan status ya/tidak
- Rekomendasi: (a) bila tersedia, kalau tidak (b); tidak ada PHI yang pernah dikirim ke layanan itu (M11)

## Terjawab

(belum ada)

# PRD SIKG: Deployment dan Operasional

> Bagian dari PRD SIKG. Dokumen ini adalah sumber kebutuhan; Claude Code tidak mengubahnya tanpa permintaan eksplisit pengguna. Tidak memuat tanggal atau estimasi durasi (CLAUDE.md M15).

## 17. Deployment, cadangan, dan operasional

SIKG mendukung dua profil hosting dengan kode yang sama (ADR-0006, `docs/rules/hosting.md`): **Profil A** satu VPS atau server cloud, dan **Profil B** shared hosting (cPanel atau sejenis). Keduanya cukup untuk skala klinik ini asalkan pemulihan benar-benar diuji; Profil B memiliki batas yang harus disetujui pemilik (lihat bawah).

| Aspek | Profil A: server | Profil B: shared hosting |
| --- | --- | --- |
| Lingkungan | Lokal (Docker atau Laravel Sail), staging dengan data sintetis, produksi | Sama; staging dapat berupa subdomain pada paket hosting yang sama atau paket terpisah |
| Hosting | Satu VPS atau server cloud dengan pusat data di Indonesia. Titik awal usulan: 2 vCPU, 4-8 GB RAM, SSD 80 GB atau lebih; sesuaikan setelah uji beban | Paket hosting bersama dengan pusat data di Indonesia yang memenuhi syarat di `docs/rules/hosting.md` bagian 2 (PHP 8.3+, MySQL 8, cron, `.htaccess`, akar dokumen ke `public/`) |
| Stack server | Nginx, PHP-FPM 8.3 atau lebih baru, MySQL 8, Redis (opsional), Supervisor untuk queue worker, cron untuk scheduler | Apache atau LiteSpeed, PHP 8.3+ dari panel, MySQL 8; cron menggerakkan scheduler dan antrean; tanpa Redis, Supervisor, atau Node |
| Cache, sesi, antrean | `database` atau Redis | `database` |
| CI/CD | GitHub Actions: lint, type-check, tes, build aset Vite, lalu deploy tanpa downtime (rilis berbasis symlink) | GitHub Actions menghasilkan arsip rilis siap unggah (kode, `vendor/`, `public/build`); rilis lewat unggah dan migrasi, dengan mode pemeliharaan singkat |
| Migrasi database | Dijalankan saat deploy dengan pola expand-contract agar versi lama dan baru kode bisa hidup berdampingan sesaat | Sama, dijalankan lewat SSH/Terminal atau entri cron sekali jalan. Tidak ada route web untuk migrasi |
| Cadangan database | Cadangan penuh harian ditambah binlog untuk pemulihan ke titik waktu; dienkripsi | Dump berkala lewat cron hosting (interval menentukan RPO, usulan maksimal 6 jam); dienkripsi sebelum keluar dari server |
| Cadangan berkas | Berkas unggahan disinkronkan harian ke penyimpanan objek di penyedia berbeda dari server utama | Sama; berkas sudah terenkripsi di aplikasi |
| Retensi cadangan | Harian 30 hari, bulanan 12 bulan, tahunan disimpan terus karena kewajiban retensi rekam medis 25 tahun | Sama (di tujuan jarak jauh) |
| Uji pemulihan | Restore ke server uji secara berkala, hasil dicatat | Sama |
| Pemantauan | Uptime check, Laravel Pulse, pelacak galat (Sentry atau setara) | Uptime check eksternal ke `/up` dan `/health`, pelacak galat, halaman Kesiapan Lingkungan (SYS-06) dan denyut cron (SYS-07); Pulse tidak dipakai |
| Peringatan | Email atau WhatsApp ke penanggung jawab teknis untuk: cadangan gagal, queue macet, disk di atas 80%, lonjakan galat | Sama; peringatan cron mati datang dari layanan pemantau eksternal karena aplikasi tidak dapat mendeteksi cron-nya sendiri yang mati |
| Log | Log aplikasi terstruktur dengan rotasi dan retensi 90 hari; audit log tetap di database | Sama; ukuran log dipantau terhadap kuota disk |
| TLS | HTTPS dengan sertifikat otomatis (Let's Encrypt) dan pembaruan otomatis | HTTPS dari AutoSSL atau Let's Encrypt penyedia, dengan HSTS dari aplikasi |
| Runbook | Deploy, rollback, restore, rotasi kunci, penambahan pengguna baru | Sama, ditambah pindah penyedia dan jalur naik ke server (`docs/operations.md` bagian 2.5) |

**Batas yang diterima pada Profil B** (disetujui pemilik sebelum memilihnya): RPO sebesar interval dump, bukan 1 jam; ketersediaan mengikuti SLA penyedia; performa bergantung pada pengguna lain di server dan wajib diuji pada hosting sasaran; laporan berat dan pengingat tertunda hingga satu interval cron; enkripsi disk tidak dapat diatur (dikompensasi enkripsi di aplikasi); tidak ada pembaruan layar waktu nyata di luar polling. Bila uji beban atau uji pemulihan tidak memenuhi NFR, klinik pindah ke Profil A tanpa mengubah kode.

**Migrasi data awal**

1. Admin klinik mengisi template Excel pasien yang disediakan sistem (nama, tanggal lahir, jenis kelamin, NIK, HP, alamat, alergi, penyakit sistemik).
2. Impor berjalan dengan pratinjau: baris valid, baris galat, dan dugaan duplikat ditampilkan sebelum dikonfirmasi (PAS-08).
3. Data tanpa NIK atau dengan duplikat ditandai perlu dilengkapi, bukan ditolak.
4. Sampel 100 pasien diverifikasi terhadap buku atau Excel sumber.
5. Staf berlatih di staging sebelum cutover; setelahnya buku menjadi arsip baca saja.

**Pelatihan dan serah terima**

- Sesi pelatihan per peran, panduan satu halaman per peran, dan pendampingan setelah go-live.
- Serah terima mencakup kode, dokumentasi, akses server, runbook, dan daftar akun pihak ketiga (domain, hosting, penyedia WhatsApp) atas nama klinik.

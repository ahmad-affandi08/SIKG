# PRD SIKG: Deployment dan Operasional

> Bagian dari PRD SIKG. Dokumen ini adalah sumber kebutuhan; Claude Code tidak mengubahnya tanpa permintaan eksplisit pengguna. Tidak memuat tanggal atau estimasi durasi (CLAUDE.md M15).

## 17. Deployment, cadangan, dan operasional

Satu server produksi dengan cadangan terenkripsi di lokasi terpisah sudah cukup untuk skala klinik ini, asalkan pemulihan benar-benar diuji.

| Aspek | Keputusan |
| --- | --- |
| Lingkungan | Lokal (Docker atau Laravel Sail), staging dengan data sintetis, produksi |
| Hosting | Satu VPS atau server cloud dengan pusat data di Indonesia. Titik awal usulan: 2 vCPU, 4-8 GB RAM, SSD 80 GB atau lebih; sesuaikan setelah uji beban |
| Stack server | Nginx, PHP-FPM 8.3 atau lebih baru, MySQL 8, Redis, Supervisor untuk queue worker, cron untuk scheduler |
| CI/CD | GitHub Actions: lint, type-check, tes, build aset Vite, lalu deploy tanpa downtime (rilis berbasis symlink) |
| Migrasi database | Dijalankan saat deploy dengan pola expand-contract agar versi lama dan baru kode bisa hidup berdampingan sesaat |
| Cadangan database | Cadangan penuh harian ditambah binlog untuk pemulihan ke titik waktu; dienkripsi |
| Cadangan berkas | Berkas unggahan disinkronkan harian ke penyimpanan objek di penyedia berbeda dari server utama |
| Retensi cadangan | Harian 30 hari, bulanan 12 bulan, tahunan disimpan terus karena kewajiban retensi rekam medis 25 tahun |
| Uji pemulihan | Restore ke server uji secara berkala, hasil dicatat |
| Pemantauan | Uptime check, Laravel Pulse, pelacak galat (Sentry atau setara) |
| Peringatan | Email atau WhatsApp ke penanggung jawab teknis untuk: cadangan gagal, queue macet, disk di atas 80%, lonjakan galat |
| Log | Log aplikasi terstruktur dengan rotasi dan retensi 90 hari; audit log tetap di database |
| TLS | HTTPS dengan sertifikat otomatis (Let's Encrypt) dan pembaruan otomatis |
| Runbook | Deploy, rollback, restore, rotasi kunci, penambahan pengguna baru |

**Migrasi data awal**

1. Admin klinik mengisi template Excel pasien yang disediakan sistem (nama, tanggal lahir, jenis kelamin, NIK, HP, alamat, alergi, penyakit sistemik).
2. Impor berjalan dengan pratinjau: baris valid, baris galat, dan dugaan duplikat ditampilkan sebelum dikonfirmasi (PAS-08).
3. Data tanpa NIK atau dengan duplikat ditandai perlu dilengkapi, bukan ditolak.
4. Sampel 100 pasien diverifikasi terhadap buku atau Excel sumber.
5. Staf berlatih di staging sebelum cutover; setelahnya buku menjadi arsip baca saja.

**Pelatihan dan serah terima**

- Sesi pelatihan per peran, panduan satu halaman per peran, dan pendampingan setelah go-live.
- Serah terima mencakup kode, dokumentasi, akses server, runbook, dan daftar akun pihak ketiga (domain, hosting, penyedia WhatsApp) atas nama klinik.

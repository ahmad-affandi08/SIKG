# PRD SIKG: Kebutuhan Non-Fungsional

> Bagian dari PRD SIKG. Dokumen ini adalah sumber kebutuhan; Claude Code tidak mengubahnya tanpa permintaan eksplisit pengguna. Tidak memuat tanggal atau estimasi durasi (CLAUDE.md M15).

## 8. Kebutuhan non-fungsional

Sistem harus cepat di jam sibuk resepsionis, tidak kehilangan data, dan bisa dipulihkan dalam hitungan jam; angka di bawah menjadi kriteria uji sebelum rilis.

| ID | Kategori | Kebutuhan | Cara verifikasi |
| --- | --- | --- | --- |
| NFR-01 | Performa | Halaman utama dimuat kurang dari 1,5 detik (p95) di jaringan klinik. Pencarian pasien kurang dari 300 ms (p95) pada 50.000 pasien dan 500.000 kunjungan. Laporan harian kurang dari 3 detik. | Uji beban k6, Laravel Pulse |
| NFR-02 | Ketersediaan | 99,5% pada jam praktik; perawatan terjadwal hanya di luar jam praktik. | Pemantauan uptime |
| NFR-03 | Cadangan dan pemulihan | Cadangan penuh harian ditambah binlog MySQL; RPO maksimal 1 jam, RTO maksimal 4 jam; uji pemulihan berkala. | Catatan uji pemulihan |
| NFR-04 | Keamanan | Sesuai bagian 14. | Checklist keamanan dan uji penetrasi dasar sebelum rilis |
| NFR-05 | Integritas data | Transaksi untuk semua operasi keuangan dan stok; foreign key aktif; uang disimpan sebagai bilangan bulat rupiah. | Uji unit domain, uji migrasi |
| NFR-06 | Auditabilitas | Perubahan data sensitif dan pembukaan rekam medis tercatat; log tidak dapat diubah lewat aplikasi. | SYS-01 |
| NFR-07 | Kegunaan | Tindakan tersering (daftar, check-in, input tindakan, bayar) maksimal 3 klik dari beranda; target sentuh minimal 44 px. | Uji dengan 5 pengguna nyata |
| NFR-08 | Responsif dan aksesibilitas | Layar 360 sampai 1920 px; kontras WCAG AA; navigasi keyboard di layar resepsionis dan kasir. | Audit Lighthouse dan uji manual |
| NFR-09 | Kompatibilitas | Dua versi terakhir Chrome, Edge, Firefox, Safari; Safari iPad dan Chrome Android. | Matriks uji peramban |
| NFR-10 | Skalabilitas | Satu server cukup untuk 10 pengguna bersamaan dan 3 cabang; queue, cache, dan storage dapat dipisah tanpa mengubah kode domain. | Uji beban |
| NFR-11 | Pemeliharaan | Cakupan tes domain layer minimal 70%; lint, type-check, dan analisis statis wajib lulus di CI; migrasi dapat diulang dari nol. | Pipeline CI |
| NFR-12 | Lokalisasi | Antarmuka bahasa Indonesia; tanggal DD/MM/YYYY; format Rp 1.250.000; zona waktu per klinik. | Uji manual |
| NFR-13 | Observabilitas | Log terstruktur, pelacakan galat (Sentry atau setara), endpoint health check. | Dashboard pemantauan |
| NFR-14 | Retensi data | Data rekam medis elektronik disimpan paling singkat 25 tahun sejak kunjungan terakhir pasien (Permenkes 24/2022 Pasal 39 ayat 1). Tidak ada hapus permanen lewat aplikasi. | Pasal 39; uji bahwa UI tidak menyediakan hapus permanen |
| NFR-15 | Lokasi data | Bila memakai penyedia hosting, penyimpanan data berada di fasilitas dalam negeri (lihat Pasal 22 Permenkes 24/2022). | Kontrak hosting |

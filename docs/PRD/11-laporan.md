# PRD SIKG: Pelaporan dan Dashboard

> Bagian dari PRD SIKG. Dokumen ini adalah sumber kebutuhan; Claude Code tidak mengubahnya tanpa permintaan eksplisit pengguna. Tidak memuat tanggal atau estimasi durasi (CLAUDE.md M15).

## 15. Pelaporan dan dashboard

Pemilik klinik harus bisa melihat kondisi bisnis hari ini dalam satu layar, dan Admin harus bisa menutup bulan tanpa merekap manual.

| ID | Laporan | Isi utama | Pengguna |
| --- | --- | --- | --- |
| LAP-01 | Ringkasan harian | Pendapatan per metode bayar, jumlah kunjungan, pasien baru, tindakan terbanyak, piutang baru | Pemilik, Admin, Kasir |
| LAP-02 | Pendapatan | Harian dan bulanan, per dokter, per kategori tindakan, per metode bayar | Pemilik, Admin |
| LAP-03 | Jasa dokter | Rekap per periode berdasarkan skema jasa di master dokter | Pemilik, Admin |
| LAP-04 | Kunjungan | Jumlah per hari, pasien baru dan lama, janji tidak datang, waktu tunggu | Pemilik, Admin |
| LAP-05 | Tindakan | Frekuensi dan nilai per jenis tindakan | Pemilik, Admin, Dokter |
| LAP-06 | Piutang dan DP | Tagihan belum lunas, umur piutang, DP belum terpakai | Pemilik, Admin, Kasir |
| LAP-07 | Stok | Stok saat ini, di bawah minimum, hampir kedaluwarsa, pergerakan stok | Pemilik, Admin, Perawat |
| LAP-08 | Tutup kas | Per kasir per hari: sistem, fisik, selisih | Pemilik, Admin, Kasir |
| LAP-09 | Kelengkapan rekam medis | Kunjungan tanpa diagnosa, tindakan, atau tanda tangan | Admin, Dokter |
| LAP-10 | Aktivitas pengguna | Ringkasan audit log per pengguna dan periode | Pemilik, Admin |

**Dashboard Pemilik** menampilkan pendapatan hari ini dan bulan ini dibanding bulan lalu, jumlah kunjungan, tingkat janji tidak datang, total piutang, dan jumlah stok menipis, ditambah grafik pendapatan 30 hari terakhir.

**Aturan umum laporan**

- Semua laporan memiliki filter rentang tanggal; laporan keuangan juga bisa difilter per dokter dan per metode bayar.
- Ekspor ke PDF dan Excel (XLSX).
- Angka dihitung di server dengan query agregasi, bukan di peramban. Laporan yang melebihi 5 detik dijalankan lewat antrean dan hasilnya diberi notifikasi. Pada shared hosting antrean digerakkan cron sehingga laporan berat dapat menunggu satu interval cron; layar menampilkan status "menunggu" lalu "berjalan" (SYS-07), dan laporan dipecah berpotongan agar muat dalam batas waktu hosting.
- Laporan keuangan hanya memakai tagihan berstatus final; tagihan yang di-void tampil terpisah.

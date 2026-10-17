# PRD SIKG: Alur Kerja

> Bagian dari PRD SIKG. Dokumen ini adalah sumber kebutuhan; Claude Code tidak mengubahnya tanpa permintaan eksplisit pengguna. Tidak memuat tanggal atau estimasi durasi (CLAUDE.md M15).

## 5. Alur kerja utama

Satu pasien melewati delapan langkah dari janji sampai laporan; setiap langkah dimiliki satu peran dan meninggalkan satu data yang dipakai langkah berikutnya.

| Langkah | Peran | Hasil | Kebutuhan terkait |
| --- | --- | --- | --- |
| 1. Daftar dan janji | Resepsionis | Data pasien (nomor RM), janji temu tanpa bentrok | PAS, JDW-01..02 |
| 2. Check-in dan antrean | Resepsionis | Status Check-in, nomor antrean, kunjungan dibuat | JDW-04..05, RM-01 |
| 3. Anamnesis | Perawat | Keluhan utama, tanda vital, riwayat medis diperbarui | RM-02, PAS-04 |
| 4. Pemeriksaan | Dokter | SOAP, odontogram, diagnosa | RM-03..04, ODO |
| 5. Tindakan dan resep | Dokter | Tindakan per gigi, resep, berkas klinis | RM-05, RM-08, OBT |
| 6. Tutup kunjungan | Dokter | Catatan terkunci, tindakan masuk draft tagihan | RM-07, KSR-01 |
| 7. Tagihan dan bayar | Kasir | Invoice, pembayaran (bisa sebagian), kwitansi | KSR |
| 8. Pelaporan dan kontrol | Pemilik, Admin | Laporan harian, tutup kas, janji kontrol | LAP, KSR-08, JDW |

**Alur alternatif**

- **Pasien walk-in:** langkah 1 dipersingkat; pasien langsung masuk antrean harian (JDW-05).
- **Pasien baru:** pendaftaran memeriksa duplikat (PAS-03) dan mencatat persetujuan data (PAS-05); pasien di bawah 18 tahun wajib punya data wali (PAS-06).
- **Pasien membatalkan atau tidak datang:** status Batal atau Tidak datang dengan alasan (JDW-04, JDW-08); slot dibebaskan.
- **Perawatan multi-kunjungan:** rencana perawatan berlanjut ke janji berikutnya (RM-06).
- **Pembayaran sebagian atau DP:** sisa tercatat sebagai piutang pasien (KSR-04).
- **Koreksi tagihan:** void hanya oleh Admin atau Pemilik dengan alasan; uang yang sudah masuk dikembalikan lewat transaksi refund (KSR-07).
- **Koreksi rekam medis:** perbaikan langsung hanya oleh pengguna berwenang dalam 2x24 jam sejak input; setelah itu lewat addendum (RM-07).
- **Peringatan alergi:** tampil di langkah 3 sampai 5 bila pasien punya alergi atau penyakit sistemik terkait (PAS-04, OBT-02).

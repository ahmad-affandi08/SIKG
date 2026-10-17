# Status kebutuhan

Dibarui oleh Claude Code setiap kali satu kebutuhan selesai (lulus `scripts/verify.sh`). Ringkasan: `python3 scripts/status.py`.

Format baris: `- [ ] ID | prioritas | judul`. Ganti `[ ]` menjadi `[x]` hanya setelah Definition of Done terpenuhi. Jangan menambahkan tanggal atau estimasi.

## Fase 1

### Must

- [x] AUTH-01 | M | Login dengan email atau username dan kata sandi
- [x] AUTH-02 | M | Peran dan izin granular per modul sesuai matriks bagian 4
- [x] AUTH-03 | M | Kelola pengguna
- [x] AUTH-04 | M | Sesi berakhir otomatis setelah tidak aktif (default 30 menit, dapat diatur)
- [x] AUTH-06 | M | Pengguna mengganti kata sandinya sendiri; kata sandi sementara wajib diganti saat masuk pertama (Q-9)
- [x] MST-01 | M | Profil klinik
- [x] MST-02 | M | Dokter
- [x] MST-03 | M | Kursi atau unit perawatan, dapat dinonaktifkan
- [x] MST-04 | M | Katalog tindakan
- [x] MST-05 | M | Katalog obat dan bahan
- [x] MST-06 | M | Metode pembayaran
- [x] MST-07 | M | Master diagnosa berbasis ICD-10 (bab K00-K14 penyakit rongga mulut) dengan ...
- [x] MST-08 | M | Perubahan tarif tidak mengubah tagihan lama
- [x] PAS-01 | M | Pasien baru
- [x] PAS-02 | M | Pencarian pasien berdasarkan nama, nomor RM, NIK, atau HP
- [x] PAS-03 | M | Deteksi duplikat
- [x] PAS-04 | M | Riwayat medis
- [x] PAS-05 | M | Persetujuan pemrosesan data pribadi dicatat (tanggal, versi teks, siapa yang ...
- [x] PAS-06 | M | Pasien di bawah 18 tahun wajib memiliki data wali atau penanggung jawab
- [x] PAS-08 | M | Impor pasien dari Excel dengan pratinjau dan laporan kesalahan per baris
- [x] JDW-01 | M | Buat janji
- [x] JDW-02 | M | Cegah bentrok
- [x] JDW-03 | M | Kalender harian dan mingguan per dokter dan per kursi
- [x] JDW-04 | M | Status janji
- [x] JDW-05 | M | Pasien walk-in masuk antrean langsung dengan nomor antrean harian
- [x] JDW-08 | M | Jadwal ulang dan pembatalan menyimpan alasan
- [x] RM-01 | M | Kunjungan dibuat saat check-in
- [x] RM-02 | M | Anamnesis
- [x] RM-03 | M | Pemeriksaan klinis ekstra oral dan intra oral dengan format SOAP
- [x] RM-04 | M | Diagnosa dengan pencarian ICD-10, boleh lebih dari satu, dapat ditautkan ke ...
- [x] RM-05 | M | Tindakan dipilih dari katalog dengan gigi, permukaan, atau rahang sesuai ...
- [x] RM-07 | M | Kunci catatan
- [x] RM-08 | M | Unggah foto klinis atau rontgen (JPG, PNG, PDF, maksimal 10 MB per berkas) ke ...
- [x] RM-11 | M | Linimasa riwayat pasien
- [x] ODO-01 | M | Odontogram interaktif gigi dewasa (32 gigi) dan gigi sulung (20 gigi) dengan ...
- [x] ODO-02 | M | Setiap gigi memiliki lima permukaan (oklusal atau insisal, mesial, distal, ...
- [x] ODO-03 | M | Kondisi gigi
- [x] ODO-04 | M | Odontogram tiap kunjungan disimpan sebagai snapshot
- [x] OBT-01 | M | Resep dari kunjungan
- [x] OBT-03 | M | Cetak resep dengan kop klinik dan nama dokter
- [x] OBT-04 | M | Obat yang dijual di klinik masuk tagihan dan mengurangi stok otomatis
- [x] OBT-05 | M | Stok masuk, stok keluar (penjualan dan pemakaian), dan penyesuaian stok ...
- [x] KSR-01 | M | Tagihan dibuat otomatis dari tindakan dan obat pada kunjungan
- [x] KSR-02 | M | Diskon per item atau per tagihan (persen atau nominal) dengan alasan wajib
- [x] KSR-03 | M | Pembayaran tunai, transfer, QRIS, debit, kredit, termasuk pembayaran campuran ...
- [x] KSR-04 | M | Uang muka (DP) dan pembayaran sebagian
- [x] KSR-06 | M | Invoice dan kwitansi bernomor urut otomatis, cetak A4 dan printer termal 80 mm, ...
- [x] KSR-07 | M | Pembatalan tagihan (void) hanya oleh Admin atau Pemilik dengan alasan
- [x] KSR-08 | M | Tutup kas harian
- [x] SYS-01 | M | Audit log
- [x] SYS-02 | M | Cadangan basis data dan berkas unggahan otomatis harian, dengan uji pemulihan ...
- [x] SYS-03 | M | Soft delete untuk data klinis dan keuangan
- [x] SYS-04 | M | Pengaturan
- [x] SYS-06 | M | Pemeriksaan kesiapan lingkungan (perintah dan halaman Admin)
- [x] SYS-07 | M | Denyut cron dan antrean, status `/health`

### Should

- [x] AUTH-05 | S | Autentikasi dua faktor (TOTP) opsional untuk Pemilik dan Admin
- [x] AUTH-07 | S | Halaman Peran dan izin: Pemilik dan Admin menyesuaikan izin per peran, tercatat di audit log (Q-8)
- [x] PAS-07 | S | Penggabungan data pasien ganda oleh Admin, tercatat di audit log
- [x] JDW-06 | S | Papan antrean untuk resepsionis dan layar dokter, diperbarui otomatis maksimal ...
- [x] JDW-07 | S | Pengingat WhatsApp H-1 dan hari-H
- [x] RM-06 | S | Rencana perawatan multi-kunjungan dengan estimasi biaya dan status tiap langkah ...
- [x] RM-09 | S | Informed consent
- [x] RM-10 | S | Surat keterangan dan rujukan dicetak dari template
- [x] ODO-05 | S | Tindakan pada gigi dapat memperbarui kondisi gigi (contoh
- [x] ODO-06 | S | Cetak odontogram ke PDF
- [x] OBT-02 | S | Peringatan alergi
- [x] OBT-06 | S | Peringatan stok di bawah minimum dan obat yang kedaluwarsa dalam 30 hari
- [x] KSR-05 | S | Paket cicilan (contoh
- [x] LAP-01 | S | Laporan ringkasan harian
- [x] LAP-02 | S | Laporan pendapatan
- [x] LAP-03 | S | Laporan jasa dokter
- [x] LAP-04 | S | Laporan kunjungan
- [x] LAP-05 | S | Laporan tindakan
- [x] LAP-06 | S | Laporan piutang dan DP
- [x] LAP-07 | S | Laporan stok
- [x] LAP-08 | S | Laporan tutup kas
- [x] LAP-09 | S | Laporan kelengkapan rekam medis
- [x] LAP-10 | S | Laporan aktivitas pengguna

### Could

- [x] ODO-07 | C | Bandingkan odontogram antar kunjungan berdampingan
- [x] KSR-09 | C | Pengaturan pajak opsional (default 0%)
- [x] SYS-05 | C | Notifikasi dalam aplikasi (janji baru, stok menipis)

## Fase 2 (dimulai setelah Gerbang 2; P2-01 lebih dulu)

- [x] P2-01 | Integrasi SATUSEHAT (wajib regulasi, dikerjakan pertama)
- [x] P2-02 | Penjamin asuransi dan perusahaan
- [x] P2-03 | Pengingat WhatsApp otomatis
- [x] P2-04 | Ortodonti
- [x] P2-05 | Stok lanjutan
- [x] P2-06 | Pesanan lab gigi
- [ ] P2-07 | Informed consent digital
- [ ] P2-08 | Pengeluaran dan laba rugi sederhana
- [ ] P2-09 | Jasa dokter lanjutan

## Fase 3 (dimulai setelah Gerbang 3)

- [ ] P3-01 | Booking online pasien
- [ ] P3-02 | Mode offline (PWA)
- [ ] P3-03 | Integrasi rontgen digital
- [ ] P3-04 | Multi-cabang penuh
- [ ] P3-05 | Pembayaran online
- [ ] P3-06 | Voucher dan membership

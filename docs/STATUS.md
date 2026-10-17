# Status kebutuhan

Dibarui oleh Claude Code setiap kali satu kebutuhan selesai (lulus `scripts/verify.sh`). Ringkasan: `python3 scripts/status.py`.

Format baris: `- [ ] ID | prioritas | judul`. Ganti `[ ]` menjadi `[x]` hanya setelah Definition of Done terpenuhi. Jangan menambahkan tanggal atau estimasi.

## Fase 1

### Must

- [ ] AUTH-01 | M | Login dengan email atau username dan kata sandi
- [ ] AUTH-02 | M | Peran dan izin granular per modul sesuai matriks bagian 4
- [ ] AUTH-03 | M | Kelola pengguna
- [ ] AUTH-04 | M | Sesi berakhir otomatis setelah tidak aktif (default 30 menit, dapat diatur)
- [ ] MST-01 | M | Profil klinik
- [ ] MST-02 | M | Dokter
- [ ] MST-03 | M | Kursi atau unit perawatan, dapat dinonaktifkan
- [ ] MST-04 | M | Katalog tindakan
- [ ] MST-05 | M | Katalog obat dan bahan
- [ ] MST-06 | M | Metode pembayaran
- [ ] MST-07 | M | Master diagnosa berbasis ICD-10 (bab K00-K14 penyakit rongga mulut) dengan ...
- [ ] MST-08 | M | Perubahan tarif tidak mengubah tagihan lama
- [ ] PAS-01 | M | Pasien baru
- [ ] PAS-02 | M | Pencarian pasien berdasarkan nama, nomor RM, NIK, atau HP
- [ ] PAS-03 | M | Deteksi duplikat
- [ ] PAS-04 | M | Riwayat medis
- [ ] PAS-05 | M | Persetujuan pemrosesan data pribadi dicatat (tanggal, versi teks, siapa yang ...
- [ ] PAS-06 | M | Pasien di bawah 18 tahun wajib memiliki data wali atau penanggung jawab
- [ ] PAS-08 | M | Impor pasien dari Excel dengan pratinjau dan laporan kesalahan per baris
- [ ] JDW-01 | M | Buat janji
- [ ] JDW-02 | M | Cegah bentrok
- [ ] JDW-03 | M | Kalender harian dan mingguan per dokter dan per kursi
- [ ] JDW-04 | M | Status janji
- [ ] JDW-05 | M | Pasien walk-in masuk antrean langsung dengan nomor antrean harian
- [ ] JDW-08 | M | Jadwal ulang dan pembatalan menyimpan alasan
- [ ] RM-01 | M | Kunjungan dibuat saat check-in
- [ ] RM-02 | M | Anamnesis
- [ ] RM-03 | M | Pemeriksaan klinis ekstra oral dan intra oral dengan format SOAP
- [ ] RM-04 | M | Diagnosa dengan pencarian ICD-10, boleh lebih dari satu, dapat ditautkan ke ...
- [ ] RM-05 | M | Tindakan dipilih dari katalog dengan gigi, permukaan, atau rahang sesuai ...
- [ ] RM-07 | M | Kunci catatan
- [ ] RM-08 | M | Unggah foto klinis atau rontgen (JPG, PNG, PDF, maksimal 10 MB per berkas) ke ...
- [ ] RM-11 | M | Linimasa riwayat pasien
- [ ] ODO-01 | M | Odontogram interaktif gigi dewasa (32 gigi) dan gigi sulung (20 gigi) dengan ...
- [ ] ODO-02 | M | Setiap gigi memiliki lima permukaan (oklusal atau insisal, mesial, distal, ...
- [ ] ODO-03 | M | Kondisi gigi
- [ ] ODO-04 | M | Odontogram tiap kunjungan disimpan sebagai snapshot
- [ ] OBT-01 | M | Resep dari kunjungan
- [ ] OBT-03 | M | Cetak resep dengan kop klinik dan nama dokter
- [ ] OBT-04 | M | Obat yang dijual di klinik masuk tagihan dan mengurangi stok otomatis
- [ ] OBT-05 | M | Stok masuk, stok keluar (penjualan dan pemakaian), dan penyesuaian stok ...
- [ ] KSR-01 | M | Tagihan dibuat otomatis dari tindakan dan obat pada kunjungan
- [ ] KSR-02 | M | Diskon per item atau per tagihan (persen atau nominal) dengan alasan wajib
- [ ] KSR-03 | M | Pembayaran tunai, transfer, QRIS, debit, kredit, termasuk pembayaran campuran ...
- [ ] KSR-04 | M | Uang muka (DP) dan pembayaran sebagian
- [ ] KSR-06 | M | Invoice dan kwitansi bernomor urut otomatis, cetak A4 dan printer termal 80 mm, ...
- [ ] KSR-07 | M | Pembatalan tagihan (void) hanya oleh Admin atau Pemilik dengan alasan
- [ ] KSR-08 | M | Tutup kas harian
- [ ] SYS-01 | M | Audit log
- [ ] SYS-02 | M | Cadangan basis data dan berkas unggahan otomatis harian, dengan uji pemulihan ...
- [ ] SYS-03 | M | Soft delete untuk data klinis dan keuangan
- [ ] SYS-04 | M | Pengaturan
- [ ] SYS-06 | M | Pemeriksaan kesiapan lingkungan (perintah dan halaman Admin)
- [ ] SYS-07 | M | Denyut cron dan antrean, status `/health`

### Should

- [ ] AUTH-05 | S | Autentikasi dua faktor (TOTP) opsional untuk Pemilik dan Admin
- [ ] PAS-07 | S | Penggabungan data pasien ganda oleh Admin, tercatat di audit log
- [ ] JDW-06 | S | Papan antrean untuk resepsionis dan layar dokter, diperbarui otomatis maksimal ...
- [ ] JDW-07 | S | Pengingat WhatsApp H-1 dan hari-H
- [ ] RM-06 | S | Rencana perawatan multi-kunjungan dengan estimasi biaya dan status tiap langkah ...
- [ ] RM-09 | S | Informed consent
- [ ] RM-10 | S | Surat keterangan dan rujukan dicetak dari template
- [ ] ODO-05 | S | Tindakan pada gigi dapat memperbarui kondisi gigi (contoh
- [ ] ODO-06 | S | Cetak odontogram ke PDF
- [ ] OBT-02 | S | Peringatan alergi
- [ ] OBT-06 | S | Peringatan stok di bawah minimum dan obat yang kedaluwarsa dalam 30 hari
- [ ] KSR-05 | S | Paket cicilan (contoh

### Could

- [ ] ODO-07 | C | Bandingkan odontogram antar kunjungan berdampingan
- [ ] KSR-09 | C | Pengaturan pajak opsional (default 0%)
- [ ] SYS-05 | C | Notifikasi dalam aplikasi (janji baru, stok menipis)

## Fase 2 (dimulai setelah Gerbang 2; P2-01 lebih dulu)

- [ ] P2-01 | Integrasi SATUSEHAT (wajib regulasi, dikerjakan pertama)
- [ ] P2-02 | Penjamin asuransi dan perusahaan
- [ ] P2-03 | Pengingat WhatsApp otomatis
- [ ] P2-04 | Ortodonti
- [ ] P2-05 | Stok lanjutan
- [ ] P2-06 | Pesanan lab gigi
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

# PRD SIKG: Kebutuhan Fungsional Fase 1

> Bagian dari PRD SIKG. Dokumen ini adalah sumber kebutuhan; Claude Code tidak mengubahnya tanpa permintaan eksplisit pengguna. Tidak memuat tanggal atau estimasi durasi (CLAUDE.md M15).

## 6. Kebutuhan fungsional Fase 1

Fase 1 terdiri dari sepuluh kelompok kebutuhan; semua butir Must wajib selesai agar klinik bisa berhenti memakai buku dan Excel. Prioritas: M = Must (wajib), S = Should (penting, boleh digeser bila perlu), C = Could (bila sempat).

### 6.1 Autentikasi dan pengguna (AUTH)

| ID | Kebutuhan dan kriteria penerimaan | Prioritas |
| --- | --- | --- |
| AUTH-01 | Login dengan email atau username dan kata sandi. Kata sandi di-hash, percobaan gagal dibatasi 5 kali per menit per akun dan IP. | M |
| AUTH-02 | Peran dan izin granular per modul sesuai matriks bagian 4; dicek di Policy backend dan menyembunyikan menu di UI. | M |
| AUTH-03 | Kelola pengguna: buat, nonaktifkan, reset kata sandi. Pengguna nonaktif tidak bisa login, riwayatnya tetap utuh. | M |
| AUTH-04 | Sesi berakhir otomatis setelah tidak aktif (default 30 menit, dapat diatur). | M |
| AUTH-05 | Autentikasi dua faktor (TOTP) opsional untuk Pemilik dan Admin. | S |

### 6.2 Master data (MST)

| ID | Kebutuhan dan kriteria penerimaan | Prioritas |
| --- | --- | --- |
| MST-01 | Profil klinik: nama, alamat, logo, nomor izin, header kwitansi dan resep. | M |
| MST-02 | Dokter: nama, nomor SIP, jadwal praktik mingguan, skema jasa (persentase atau nominal per tindakan). | M |
| MST-03 | Kursi atau unit perawatan, dapat dinonaktifkan. | M |
| MST-04 | Katalog tindakan: kode, nama, kategori, tarif standar, dan cakupan (per gigi, per permukaan, per rahang, atau per kunjungan). | M |
| MST-05 | Katalog obat dan bahan: nama, satuan, harga jual, stok minimum, kelas obat untuk pengecekan alergi. | M |
| MST-06 | Metode pembayaran: tunai, transfer, QRIS, debit, kredit (pencatatan manual). | M |
| MST-07 | Master diagnosa berbasis ICD-10 (bab K00-K14 penyakit rongga mulut) dengan pencarian autocomplete. | M |
| MST-08 | Perubahan tarif tidak mengubah tagihan lama: tarif disalin ke item tagihan saat item dibuat. | M |

### 6.3 Pasien (PAS)

| ID | Kebutuhan dan kriteria penerimaan | Prioritas |
| --- | --- | --- |
| PAS-01 | Pasien baru: nama, NIK (wajib menurut Permenkes 24/2022 Pasal 14; bila belum ada, misalnya bayi atau WNA, alasannya dicatat dan diisi menyusul), tanggal lahir, jenis kelamin, alamat, nomor HP atau WhatsApp, pekerjaan, kontak darurat. Nomor RM dibuat otomatis, unik, formatnya dapat diatur (contoh RM-000001). | M |
| PAS-02 | Pencarian pasien berdasarkan nama, nomor RM, NIK, atau HP; hasil muncul kurang dari 1 detik untuk 50.000 pasien. | M |
| PAS-03 | Deteksi duplikat: peringatan saat NIK sama, atau nama + tanggal lahir + HP mirip. | M |
| PAS-04 | Riwayat medis: alergi (obat, lateks, anestesi), penyakit sistemik (diabetes, hipertensi, jantung, gangguan pembekuan darah, dll), obat rutin, kehamilan. Tampil sebagai banner peringatan di setiap layar pemeriksaan. | M |
| PAS-05 | Persetujuan pemrosesan data pribadi dicatat (tanggal, versi teks, siapa yang mencatat). | M |
| PAS-06 | Pasien di bawah 18 tahun wajib memiliki data wali atau penanggung jawab. | M |
| PAS-07 | Penggabungan data pasien ganda oleh Admin, tercatat di audit log. | S |
| PAS-08 | Impor pasien dari Excel dengan pratinjau dan laporan kesalahan per baris; baris valid tetap masuk. | M |

### 6.4 Janji temu dan antrean (JDW)

| ID | Kebutuhan dan kriteria penerimaan | Prioritas |
| --- | --- | --- |
| JDW-01 | Buat janji: pasien, dokter, kursi, tanggal, jam mulai, durasi, keluhan. | M |
| JDW-02 | Cegah bentrok: dokter atau kursi tidak boleh ganda pada slot yang sama; hormati jadwal praktik dan hari libur. | M |
| JDW-03 | Kalender harian dan mingguan per dokter dan per kursi. | M |
| JDW-04 | Status janji: Dijadwalkan, Dikonfirmasi, Check-in, Sedang diperiksa, Selesai, Batal, Tidak datang. | M |
| JDW-05 | Pasien walk-in masuk antrean langsung dengan nomor antrean harian. | M |
| JDW-06 | Papan antrean untuk resepsionis dan layar dokter, diperbarui otomatis maksimal setiap 10 detik. | S |
| JDW-07 | Pengingat WhatsApp H-1 dan hari-H. Fase 1: tombol buka wa.me dengan pesan siap kirim. Fase 2: kirim otomatis lewat penyedia WhatsApp API. | S |
| JDW-08 | Jadwal ulang dan pembatalan menyimpan alasan. | M |

### 6.5 Pemeriksaan dan rekam medis (RM)

| ID | Kebutuhan dan kriteria penerimaan | Prioritas |
| --- | --- | --- |
| RM-01 | Kunjungan dibuat saat check-in; satu kunjungan memiliki satu dokter utama. | M |
| RM-02 | Anamnesis: keluhan utama, riwayat keluhan, tanda vital (tekanan darah, nadi, suhu) oleh perawat atau dokter. | M |
| RM-03 | Pemeriksaan klinis ekstra oral dan intra oral dengan format SOAP. | M |
| RM-04 | Diagnosa dengan pencarian ICD-10, boleh lebih dari satu, dapat ditautkan ke gigi tertentu. | M |
| RM-05 | Tindakan dipilih dari katalog dengan gigi, permukaan, atau rahang sesuai cakupan tindakan, jumlah, dan catatan. Tindakan otomatis masuk draft tagihan. | M |
| RM-06 | Rencana perawatan multi-kunjungan dengan estimasi biaya dan status tiap langkah (Direncanakan, Berjalan, Selesai). | S |
| RM-07 | Kunci catatan: perbaikan hanya oleh pengguna berwenang dalam 2x24 jam sejak data diinput (Permenkes 24/2022 Pasal 30). Setelah itu, perubahan hanya lewat addendum bertanda waktu dan nama pengguna, dengan persetujuan Admin atau pimpinan klinik. | M |
| RM-08 | Unggah foto klinis atau rontgen (JPG, PNG, PDF, maksimal 10 MB per berkas) ke kunjungan atau gigi. | M |
| RM-09 | Informed consent: template per jenis tindakan, dicetak untuk tanda tangan basah. Tanda tangan digital di Fase 2. | S |
| RM-10 | Surat keterangan dan rujukan dicetak dari template. | S |
| RM-11 | Linimasa riwayat pasien: seluruh kunjungan, tindakan, resep, dan berkas dalam satu halaman. | M |

### 6.6 Odontogram (ODO)

| ID | Kebutuhan dan kriteria penerimaan | Prioritas |
| --- | --- | --- |
| ODO-01 | Odontogram interaktif gigi dewasa (32 gigi) dan gigi sulung (20 gigi) dengan penomoran FDI. | M |
| ODO-02 | Setiap gigi memiliki lima permukaan (oklusal atau insisal, mesial, distal, bukal atau labial, lingual atau palatal) yang kondisinya dapat dicatat terpisah. | M |
| ODO-03 | Kondisi gigi: sehat, karies, tambalan, mahkota, perawatan saluran akar, impaksi, hilang, sisa akar, implan, gigi tiruan atau jembatan, dan lainnya; daftar kondisi dapat diatur Admin. | M |
| ODO-04 | Odontogram tiap kunjungan disimpan sebagai snapshot; odontogram terkini dihitung dari snapshot terakhir. | M |
| ODO-05 | Tindakan pada gigi dapat memperbarui kondisi gigi (contoh: tambal menjadi tambalan) setelah dikonfirmasi dokter. | S |
| ODO-06 | Cetak odontogram ke PDF. | S |
| ODO-07 | Bandingkan odontogram antar kunjungan berdampingan. | C |

### 6.7 Resep dan stok (OBT)

| ID | Kebutuhan dan kriteria penerimaan | Prioritas |
| --- | --- | --- |
| OBT-01 | Resep dari kunjungan: obat dari katalog atau teks bebas, dosis, frekuensi, durasi, aturan pakai. | M |
| OBT-02 | Peringatan alergi: bila kelas obat cocok dengan alergi pasien, tampil peringatan sebelum resep disimpan. | S |
| OBT-03 | Cetak resep dengan kop klinik dan nama dokter. | M |
| OBT-04 | Obat yang dijual di klinik masuk tagihan dan mengurangi stok otomatis. | M |
| OBT-05 | Stok masuk, stok keluar (penjualan dan pemakaian), dan penyesuaian stok (opname) dengan alasan wajib. | M |
| OBT-06 | Peringatan stok di bawah minimum dan obat yang kedaluwarsa dalam 30 hari. | S |

### 6.8 Tagihan dan pembayaran (KSR)

| ID | Kebutuhan dan kriteria penerimaan | Prioritas |
| --- | --- | --- |
| KSR-01 | Tagihan dibuat otomatis dari tindakan dan obat pada kunjungan; kasir dapat menambah atau mengubah item sebelum dibayar sesuai izin. | M |
| KSR-02 | Diskon per item atau per tagihan (persen atau nominal) dengan alasan wajib; batas diskon dapat diatur per peran. | M |
| KSR-03 | Pembayaran tunai, transfer, QRIS, debit, kredit, termasuk pembayaran campuran dalam satu tagihan. | M |
| KSR-04 | Uang muka (DP) dan pembayaran sebagian; sisa tagihan tercatat sebagai piutang pasien. | M |
| KSR-05 | Paket cicilan (contoh: perawatan kawat gigi) dengan jadwal cicilan dan penanda jatuh tempo. | S |
| KSR-06 | Invoice dan kwitansi bernomor urut otomatis, cetak A4 dan printer termal 80 mm, PDF dapat dibagikan. | M |
| KSR-07 | Pembatalan tagihan (void) hanya oleh Admin atau Pemilik dengan alasan. Pembayaran tidak dihapus; koreksi dicatat sebagai transaksi pembalik (retur atau refund). | M |
| KSR-08 | Tutup kas harian: kasir mengisi uang fisik, sistem menghitung selisih terhadap catatan. | M |
| KSR-09 | Pengaturan pajak opsional (default 0%). Layanan kesehatan umumnya dibebaskan PPN, namun konfirmasikan dengan konsultan pajak klinik. | C |

### 6.9 Laporan (LAP)

Rincian laporan ada di bagian 15.

### 6.10 Sistem (SYS)

| ID | Kebutuhan dan kriteria penerimaan | Prioritas |
| --- | --- | --- |
| SYS-01 | Audit log: siapa, kapan, aksi apa, nilai sebelum dan sesudah untuk data pasien, rekam medis, tagihan, dan pengguna. | M |
| SYS-02 | Cadangan basis data dan berkas unggahan otomatis harian, dengan uji pemulihan terjadwal. | M |
| SYS-03 | Soft delete untuk data klinis dan keuangan; tidak ada hapus permanen lewat antarmuka. | M |
| SYS-04 | Pengaturan: zona waktu, format nomor RM dan invoice, jam operasional, hari libur. | M |
| SYS-05 | Notifikasi dalam aplikasi (janji baru, stok menipis). | C |

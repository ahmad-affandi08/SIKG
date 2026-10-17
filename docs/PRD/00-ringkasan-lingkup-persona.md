# PRD SIKG: Ringkasan, Lingkup, dan Persona

> Bagian dari PRD SIKG. Dokumen ini adalah sumber kebutuhan; Claude Code tidak mengubahnya tanpa permintaan eksplisit pengguna. Tidak memuat tanggal atau estimasi durasi (CLAUDE.md M15).

## 1. Ringkasan eksekutif

SIKG (Sistem Informasi Klinik Gigi, nama kerja) adalah aplikasi web untuk menjalankan seluruh operasional klinik gigi umum non-BPJS dalam satu sistem: pendaftaran, jadwal, rekam medis dan odontogram, resep, kasir, stok, dan laporan.

| Aspek | Keputusan |
| --- | --- |
| Jenis klinik | Klinik gigi umum, pasien umum (tanpa BPJS) |
| Skala awal | 1 klinik, 1-3 dokter, 1-3 kursi (unit); desain data siap multi-cabang |
| Pengguna | Pemilik, admin, resepsionis, dokter gigi, perawat, kasir |
| Penjamin pasien | Fase 1: pasien umum. Fase 2: asuransi swasta dan perusahaan |
| Backend | Laravel 13, arsitektur DDD berbentuk modular monolith, MySQL 8 |
| Frontend | Inertia.js + React + TypeScript, Tailwind CSS 4, Zod, TanStack Query |
| Perangkat | Peramban di PC resepsionis/kasir dan PC atau tablet ruang periksa; printer kwitansi dan resep |
| Mode operasi | Online dengan cadangan data otomatis; mode offline di luar Fase 1 |

Fase 1 (MVP) cukup untuk menggantikan buku dan Excel sepenuhnya: pasien, jadwal, pemeriksaan dengan odontogram, resep, tagihan dan pembayaran, serta laporan harian. Fase 2 menambah integrasi SATUSEHAT (wajib regulasi), penjamin, pengingat otomatis, stok lanjutan, lab gigi, dan ortodonti; Fase 3 menambah portal pasien dan integrasi lanjutan.

## 2. Latar belakang dan sasaran

Klinik yang masih memakai buku dan Excel kehilangan waktu dan uang di lima titik: data pasien tersebar, odontogram digambar tangan, tagihan dihitung manual, pasien lupa jadwal, dan laporan baru jadi setelah akhir bulan.

**Masalah yang diselesaikan**

- Rekam medis kertas sulit dicari dan mudah hilang; riwayat alergi atau penyakit sistemik tidak terlihat saat tindakan.
- Tarif dan diskon dihitung manual, sehingga tagihan bisa salah dan kas harian sulit dicocokkan.
- Pasien tidak datang tanpa kabar, kursi dokter kosong.
- Stok obat dan bahan habis tanpa peringatan.
- Jasa dokter dan pendapatan harian baru dihitung di akhir bulan.

**Sasaran dan ukuran keberhasilan** (target adalah usulan awal; sepakati dengan pemilik klinik)

| Sasaran | Metrik | Target usulan | Cara ukur |
| --- | --- | --- | --- |
| Pendaftaran cepat | Waktu daftar pasien baru / pasien lama | < 3 menit / < 1 menit | Sampel waktu saat uji pengguna |
| Rekam medis lengkap | Kunjungan selesai dengan diagnosa dan tindakan terisi | 100% | Laporan kelengkapan |
| Kurangi pasien tidak datang | Tingkat no-show | Turun 30% dalam 3 bulan setelah pengingat aktif | Status janji temu |
| Kas akurat | Selisih kas harian sistem vs fisik | Rp 0 | Laporan tutup kas |
| Laporan instan | Waktu muat laporan harian | < 3 detik | Log performa |
| Tidak ada tindakan tanpa tagihan | Tindakan yang tidak tertaut ke tagihan | 0 | Query rekonsiliasi |

Belum ada data awal klinik, jadi seluruh target di atas perlu dikonfirmasi sebelum dikunci.

## 3. Ruang lingkup, asumsi, dan batasan

Fase 1 mencakup modul inti untuk satu klinik dengan pasien umum; BPJS, integrasi alat rontgen, dan akuntansi penuh sengaja berada di luar lingkup.

**Di dalam lingkup (Fase 1)**

- Autentikasi, peran, dan hak akses
- Master data: profil klinik, dokter, kursi, tindakan, tarif, obat, diagnosa
- Pasien dan riwayat medis dasar
- Janji temu, antrean, dan pasien walk-in
- Pemeriksaan: anamnesis, odontogram, diagnosa, tindakan, rencana perawatan
- Resep dan stok obat sederhana
- Tagihan, pembayaran, DP, cicilan dasar, kwitansi, tutup kas
- Laporan harian dan bulanan, termasuk jasa dokter
- Impor pasien dari Excel, unggah foto atau rontgen, audit log, cadangan data

**Di luar lingkup**

- Integrasi BPJS (P-Care, V-Claim, antrean online)
- Aplikasi mobile native (Fase 1 cukup web responsif)
- Integrasi langsung alat rontgen (DICOM/PACS)
- Akuntansi lengkap (jurnal, neraca), payroll, dan SDM
- Payment gateway dan telemedisin
- Integrasi SATUSEHAT dikerjakan di Fase 2 (wajib regulasi, lihat bagian 7 dan 14); Fase 1 menyiapkan struktur data dan NIK pasien agar integrasi tidak perlu membongkar sistem

**Asumsi** (hasil percakapan sebelumnya; koreksi bila pemilik klinik berkata lain)

| ID | Asumsi | Dampak bila salah |
| --- | --- | --- |
| A1 | 1-3 dokter dan 1-3 kursi di satu lokasi | Kecil: database sudah siap multi-cabang |
| A2 | Semua pasien umum, tanpa penjamin pihak ketiga di Fase 1 | Modul penjamin dimajukan dari Fase 2 |
| A3 | Belum ada rontgen digital terintegrasi; cukup unggah gambar | Integrasi DICOM menambah pekerjaan besar |
| A4 | PC resepsionis/kasir, PC atau tablet per ruang periksa, printer kwitansi dan resep | Penyesuaian tata letak layar sentuh dan cetak |
| A5 | Internet di klinik stabil | Perlu mode offline (PWA dan sinkronisasi), pekerjaan besar |
| A6 | Ada data lama di buku atau Excel; yang dimigrasi hanya data pasien | Migrasi riwayat kunjungan menambah pekerjaan |
| A7 | Bahasa Indonesia, Rupiah, zona waktu WIB (dapat diubah ke WITA/WIT) | Kecil |
| A8 | Hosting di satu server atau VPS dengan pusat data di Indonesia | Mempengaruhi biaya dan kepatuhan data |

**Batasan teknis:** stack sudah ditetapkan (Laravel 13 dengan DDD, Inertia React, MySQL, Tailwind 4, Zod, TanStack Query). Sistem berupa satu codebase (modular monolith), bukan microservice.

## 4. Persona dan hak akses

Enam peran memakai sistem dengan kebutuhan berbeda; hak akses default dibatasi seminimal mungkin sesuai tugas.

| Peran | Tugas utama | Perangkat | Kebutuhan utama |
| --- | --- | --- | --- |
| Pemilik | Memantau pendapatan, kinerja dokter, stok | Laptop atau HP | Dashboard ringkas, laporan, kontrol akses |
| Admin | Master data, pengguna, koreksi data | PC | Kelola tarif, tindakan, pengguna, audit |
| Resepsionis | Mendaftar pasien, jadwal, antrean | PC | Cari pasien cepat, buat janji, check-in |
| Dokter gigi | Pemeriksaan, odontogram, resep | PC atau tablet | Riwayat lengkap, alergi terlihat, input cepat |
| Perawat | Anamnesis, tanda vital, persiapan, pemakaian bahan | PC atau tablet | Catat data awal dan pemakaian bahan |
| Kasir | Tagihan, pembayaran, tutup kas | PC | Hitung otomatis, cetak kwitansi, rekonsiliasi |

**Matriks hak akses default.** C = buat, R = lihat, U = ubah, D = hapus (soft delete), tanda hubung = tanpa akses.

| Modul | Pemilik | Admin | Resepsionis | Dokter | Perawat | Kasir |
| --- | --- | --- | --- | --- | --- | --- |
| Pasien | R | CRUD | CRU | R | R | R |
| Jadwal dan antrean | R | CRUD | CRUD | R | R | R |
| Pemeriksaan dan rekam medis | R | R | - | CRU | CR | - |
| Odontogram | R | R | - | CRU | R | - |
| Resep | R | R | - | CRU | R | R |
| Tagihan | R | R | R | R | - | CRU |
| Pembayaran | R | R | - | - | - | CR |
| Pembatalan tagihan (void) | CR | CR | - | - | - | - |
| Stok obat dan bahan | R | CRUD | - | R | CRU | - |
| Tindakan dan tarif | CRUD | CRUD | R | R | R | R |
| Laporan keuangan | R | R | - | - | - | R (kas sendiri) |
| Laporan klinis | R | R | - | R (pasien sendiri) | - | - |
| Pengguna dan peran | CRUD | CRU (tanpa mengubah Pemilik) | - | - | - | - |
| Audit log | R | R | - | - | - | - |
| Pengaturan klinik | CRU | CRU | - | - | - | - |

Izin disimpan sebagai permission granular per aksi (contoh: `invoice.void`, `patient.update`), lalu dikelompokkan ke peran. Pemilik klinik dapat menyesuaikan peran tanpa mengubah kode. Dokter hanya dapat mengubah rekam medis yang ia tangani; koreksi oleh orang lain hanya lewat addendum.

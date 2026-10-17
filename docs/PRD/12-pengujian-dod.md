# PRD SIKG: Pengujian dan Definisi Selesai

> Bagian dari PRD SIKG. Dokumen ini adalah sumber kebutuhan; Claude Code tidak mengubahnya tanpa permintaan eksplisit pengguna. Tidak memuat tanggal atau estimasi durasi (CLAUDE.md M15).

## 16. Strategi pengujian dan definisi selesai

Pengujian dipusatkan pada tiga hal yang paling mahal bila salah: uang, hak akses, dan catatan klinis.

| Level | Alat | Cakupan | Target |
| --- | --- | --- | --- |
| Unit domain | Pest | Money, hitungan tagihan, aturan jadwal, odontogram, stok | Minimal 70% domain, 100% aturan uang |
| Fitur dan use case | Pest dengan MySQL asli (bukan SQLite) | Semua use case berprioritas Must | Seluruhnya lulus |
| Arsitektur | Pest arch test, Deptrac | Batas lapisan dan konteks | Nol pelanggaran |
| Kontrak API | Pest dan Vitest | Skema Zod terhadap respons Laravel | Semua endpoint JSON |
| Komponen frontend | Vitest, Testing Library | Form pasien, odontogram, tabel tagihan | Alur utama tiap komponen |
| End-to-end | Playwright | Daftar, janji, check-in, periksa, tagihan, bayar, tutup kas | Alur kritis tiap rilis |
| Konkurensi | Pest dengan proses paralel | Dua resepsionis memesan slot yang sama; dua kasir membayar tagihan yang sama | Tidak ada data ganda |
| Otorisasi | Pest | Matriks hak akses bagian 4 diuji otomatis per peran; uji akses antar-klinik (IDOR) | 100% sel matriks |
| Keamanan | Larastan, audit dependensi, uji manual | Unggah berkas berbahaya, rate limit, injeksi | Tanpa temuan kritis |
| Performa | k6 | NFR-01 pada data sintetis 50.000 pasien dan 500.000 kunjungan, dijalankan pada hosting sasaran | Memenuhi NFR |
| Kompatibilitas hosting | Pest, job CI | Suite fitur dengan driver `database`, tanpa Redis, dan fungsi eksekusi perintah dinonaktifkan; arch test | Selalu hijau (NFR-16) |
| Aksesibilitas | axe di Playwright | Halaman utama | Tanpa pelanggaran serius |
| Pemulihan | Skrip restore | Pulihkan cadangan ke server uji | Sukses pada setiap uji berkala |
| Penerimaan pengguna (UAT) | Skenario tertulis | Staf klinik memakai data sintetis | Disetujui pemilik |

**Definisi selesai untuk setiap fitur**

- [ ] Kriteria penerimaan terpenuhi dan didemokan ke perwakilan peran terkait
- [ ] Tes otomatis lulus di CI (unit, fitur, arsitektur, lint, type-check, analisis statis)
- [ ] Otorisasi dan audit log fitur itu sudah diuji
- [ ] Migrasi bisa dijalankan naik dan turun, dan aman dijalankan ulang
- [ ] UI diuji di PC dan tablet, termasuk cetak bila relevan
- [ ] Dokumentasi dan ADR diperbarui bila ada keputusan arsitektur
- [ ] Tidak ada data pasien nyata di lingkungan pengembangan atau uji

**Kriteria go-live Fase 1**

- [ ] Semua kebutuhan Must selesai dan lulus UAT dengan staf klinik
- [ ] Selisih kas harian nol pada setiap hari uji berturut-turut selama UAT
- [ ] Uji pemulihan cadangan berhasil dan terdokumentasi
- [ ] Pada shared hosting: halaman Kesiapan Lingkungan (SYS-06) lulus, denyut cron dan antrean (SYS-07) terpantau, dan batas Profil B disetujui pemilik
- [ ] Sampel 100 pasien hasil migrasi diverifikasi terhadap data sumber
- [ ] Pelatihan tiap peran selesai dan panduan satu halaman dibagikan
- [ ] Rencana kembali ke prosedur manual (rollback) tertulis dan disetujui pemilik
- [ ] Keputusan tertulis soal integrasi SATUSEHAT dan registrasi sistem elektronik (bagian 14)

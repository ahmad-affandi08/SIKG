# Registrasi sistem elektronik dan integrasi SATUSEHAT

Dokumen kerja untuk pemilik klinik. Isinya bahan untuk registrasi sistem elektronik kesehatan (Permenkes 24/2022) dan langkah menghidupkan integrasi SATUSEHAT di SIKG (P2-01). Bukan nasihat hukum; konfirmasikan kewajiban ke dinas kesehatan setempat atau konsultan hukum.

## 1. Bahan dokumen registrasi

| Butir | Isi untuk SIKG |
| --- | --- |
| Nama sistem | SIKG, Sistem Informasi Klinik Gigi |
| Jenis | Rekam medis elektronik dan operasional klinik gigi umum non-BPJS |
| Fitur | Pendaftaran pasien, jadwal dan antrean, rekam medis dan odontogram, resep dan stok obat, tagihan dan kasir, laporan, notifikasi, integrasi SATUSEHAT |
| Pengguna | Pemilik, Admin, Resepsionis, Dokter gigi, Perawat, Kasir (hak akses per izin sesuai matriks hak akses di PRD bagian 4) |
| Lokasi penyimpanan data | Server atau shared hosting di wilayah Indonesia yang dipilih klinik; basis data MySQL 8. Isi lokasi dan penyedia hosting di formulir registrasi |
| Keamanan | Otorisasi per izin di server, audit log dan jejak akses, enkripsi NIK dan nomor HP di kolom, kata sandi ter-hash, verifikasi dua langkah opsional, cadangan terjadwal dan uji pemulihan |
| Retensi | Tidak ada hapus permanen data klinis dan keuangan lewat aplikasi; cadangan menjaga retensi rekam medis 25 tahun |
| Pihak ketiga | SATUSEHAT (Kemenkes). Penyedia WhatsApp pilihan klinik (WhatsApp Resmi/Meta, atau penyedia tidak resmi seperti Fonnte) untuk pemberitahuan staf, kode booking, dan pengingat pasien; pemberitahuan staf tanpa data pasien, pengingat memuat nama depan, jadwal, dan nama dokter |

## 2. Variabel dan metadata yang dikirim ke SATUSEHAT

Satu kunjungan yang ditutup dikirim sebagai satu Bundle transaksi FHIR R4.

| Sumber daya FHIR | Sumber di SIKG |
| --- | --- |
| Encounter (rawat jalan, `AMB`, status selesai) | Kunjungan: waktu mulai dan tutup, pasien, dokter, lokasi, organisasi |
| Condition (kategori diagnosa kunjungan) | Diagnosa ICD-10 pada kunjungan |
| Procedure | Tindakan yang katalognya memiliki kode ICD-9-CM (isian "Kode ICD-9-CM" di katalog tindakan). Tindakan tanpa kode tidak dikirim sebagai prosedur |
| Patient (rujukan) | ID pasien SATUSEHAT hasil pencarian NIK; SIKG tidak membuat data pasien di SATUSEHAT |
| Practitioner (rujukan) | ID praktisi SATUSEHAT hasil pencarian NIK dokter |
| Organization dan Location (rujukan) | ID yang diberikan SATUSEHAT saat registrasi fasilitas, diisi di pengaturan SATUSEHAT |

Catatan klinis bebas (anamnesis, catatan SOAP), foto, dan tagihan tidak dikirim.

## 3. Langkah klinik

1. Daftarkan fasilitas dan sistem elektronik sesuai ketentuan Kemenkes; siapkan dokumen pada bagian 1 dan 2.
2. Dapatkan akses SATUSEHAT: ID organisasi, client ID dan client secret untuk staging dan produksi, serta ID lokasi (Location) klinik.
3. Di SIKG buka Pengaturan > SATUSEHAT: isi lingkungan Staging, ID organisasi, ID lokasi, client ID dan client secret, lalu "Uji koneksi".
4. Isi NIK tiap dokter di bagian "NIK dokter" (terenkripsi, tidak ditampilkan kembali). NIK pasien diisi saat pendaftaran pasien.
5. Aktifkan "Kirim kunjungan yang ditutup ke SATUSEHAT", tutup satu kunjungan uji, lalu periksa status di bagian "Pengiriman kunjungan".
6. Setelah hasil di staging sesuai, ganti lingkungan ke Produksi dengan kredensial produksi.

## 4. Perilaku pengiriman

- Hanya kunjungan yang ditutup setelah integrasi aktif yang masuk antrean; kunjungan lama tidak dikirim otomatis.
- Perintah terjadwal `sikg:satusehat:send` (tiap 5 menit lewat cron) mengirim antrean. Gangguan sementara dicoba ulang dengan jeda bertahap hingga lima kali.
- Status "Perlu tindakan" berarti data belum lengkap (misalnya NIK dokter kosong atau NIK tidak ditemukan di SATUSEHAT). Perbaiki datanya lalu tekan "Kirim ulang".
- Status "Dilewati" berarti pasien tidak punya NIK (alasan NIK kosong tercatat saat pendaftaran).
- Koreksi atau addendum setelah kunjungan terkirim belum dikirim ulang ke SATUSEHAT.

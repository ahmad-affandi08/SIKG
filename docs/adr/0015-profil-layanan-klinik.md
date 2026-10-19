# ADR-0015: Profil layanan klinik (gigi atau umum) dalam satu aplikasi

- Status: Dikerjakan atas permintaan langsung pengguna ("ini ternyata ada permintaan untuk klinik umum juga, masa harus buat baru lagi solusi", lalu "lanjut ke rencana klinik umum tahap 1"). Belum ada persetujuan tertulis untuk ID KLU-01 sampai KLU-04 (M14); lihat Q-34
- Konteks terkait: M1 (stack), M2 (antar-konteks lewat kontrak), M5 (tanpa hapus permanen), M9 (otorisasi di server), M14 (cakupan PRD), ADR-0014 (nama merek SIKLIN)

## Konteks

Aplikasi dibangun untuk klinik gigi. Calon klien berikutnya adalah klinik umum, dan kemudian klinik pratama rawat inap. Membuat aplikasi terpisah menggandakan pemeliharaan. Sebagian besar fitur (pasien, jadwal, kasir, stok obat, laporan, audit, cadangan, WhatsApp, SATUSEHAT) sama; yang khusus gigi adalah odontogram, tindakan per gigi, ortodonti, dan pesanan lab gigi.

## Keputusan

- **Satu aplikasi, profil per klinik.** `Src\Shared\Domain\ServiceProfile` (`dental`, `general`) disimpan di `clinics.settings` (JSON, kunci `service_profile`), tanpa migrasi. Klinik yang sudah ada dan klinik baru berprofil **gigi**; tidak ada yang berubah bagi mereka.
- **Pengaturan lewat satu halaman** `Pengaturan > Profil layanan` (`/settings/service-profile`, izin `setting.manage`), tercatat di audit (`clinic.service_profile_changed`, sebelum dan sesudah). Formulir Pengaturan klinik tidak dapat menimpa profil.
- **Server menutup fitur gigi.** Middleware `profile.dental` (`App\Http\Middleware\RequireDentalProfile`) menjawab 404 untuk route odontogram, ortodonti, dan pesanan lab gigi pada klinik berprofil umum, walau pengguna berizin. Menyembunyikan menu hanyalah kenyamanan (M9). Konteks lain membaca profil hanya lewat kontrak `Src\Clinic\Contracts\ServiceProfileLookup` (M2).
- **Antarmuka mengikuti profil.** Prop bersama `clinic.profile`, `clinic.dental`, dan `clinic.doctor_title` mengatur menu (Ortodonti, Pesanan lab, Kondisi gigi), tab Odontogram pada halaman Pemeriksaan, dan sebutan peran Dokter ("Dokter" pada klinik umum, "Dokter gigi" pada klinik gigi).
- **Mengganti profil tidak menghapus data** (M5). Odontogram, kasus ortodonti, dan pesanan lab yang sudah ada tetap tersimpan dan tampil kembali bila profil dikembalikan.
- **Data awal dan templat mengikuti profil.** Penyiapan awal (`StarterDataContributor`) memuat katalog tindakan dan obat umum bagi klinik umum (nonaktif, harga 0 sampai klinik mengisinya); templat bawaan informed consent dan surat keterangan tanpa kata "gigi". Referensi diagnosa ICD-10 layanan primer ditambahkan ke tabel `diagnoses` bersama semua klinik lewat migrasi (tanpa menimpa atau menghapus kode yang ada).
- **Tanda vital lengkap** (laju napas, saturasi oksigen, berat badan dalam persepuluh kilogram, tinggi badan) ditambahkan pada JSON `encounters.vitals` tanpa migrasi, berlaku untuk semua profil.
- **Profil "Umum dengan rawat inap" belum ada.** Ia ditawarkan bersama modul rawat inap (INP-*), bukan sebelumnya, agar tidak ada pilihan yang fiturnya belum ada.

## Akibat

- Kebutuhan KLU-01 sampai KLU-04 dicatat di `docs/STATUS.md` tetapi belum ada di PRD (Q-34); PRD terlindungi, jadi pengguna yang menambahkannya dan menyegel ulang.
- Kode dan nama diagnosa umum, tindakan, dan obat awal disusun penulis dan belum diverifikasi terhadap daftar resmi (lihat Q-10 dan Q-34); petugas rekam medis dan apoteker klinik wajib memeriksanya.
- Selama profil Umum, riwayat odontogram, kasus ortodonti beserta cicilannya, dan pesanan lab yang sudah ada tidak dapat dibuka (404) walau datanya utuh; selesaikan cicilan dan pesanan yang masih berjalan sebelum berganti profil. Halaman Profil layanan memberi peringatan ini. Penolakan otomatis bila masih ada yang berjalan belum dibuat.
- Master kondisi gigi (halaman pengaturan data master) tidak ditutup di server pada klinik umum; hanya menunya disembunyikan. Isinya data konfigurasi tanpa data pasien.
- BPJS tetap di luar lingkup (M14) sampai pengguna memutuskan lain secara tertulis.

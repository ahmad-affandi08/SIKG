# PRD SIKG: Keamanan, Privasi, dan Kepatuhan

> Bagian dari PRD SIKG. Dokumen ini adalah sumber kebutuhan; Claude Code tidak mengubahnya tanpa permintaan eksplisit pengguna. Tidak memuat tanggal atau estimasi durasi (CLAUDE.md M15).

## 14. Keamanan, privasi, dan kepatuhan

Rekam medis elektronik klinik gigi diwajibkan hukum, dan data pasien adalah data kesehatan yang dilindungi; karena itu poin di bawah menjadi syarat rilis, bukan fitur tambahan.

**Kewajiban regulasi yang berdampak pada sistem** (ringkasan teknis dari teks [Permenkes 24/2022](https://pasal.id/peraturan/permen/permenkes-no-24-tahun-2022), bukan nasihat hukum)

| Dasar | Kewajiban | Dampak pada SIKG |
| --- | --- | --- |
| Pasal 3 dan 45 | Klinik dan tempat praktik mandiri dokter gigi wajib menyelenggarakan rekam medis elektronik; masa peralihan sudah berakhir | SIKG menjadi rekam medis elektronik klinik; ini kewajiban, bukan pilihan |
| Pasal 9 dan 12 | Penyelenggara sistem elektronik harus terdaftar sebagai PSE sektor kesehatan; sistem diregistrasi di Kemenkes dengan dokumen: nama sistem, dokumentasi, fitur, lokasi penyimpanan data, variabel dan metadata | Siapkan dokumentasi sistem dan proses registrasi (bagian dari P2-01) |
| Pasal 10, 11, 21 | Sistem harus interoperabel, mengacu pada variabel dan metadata Kemenkes, dan terhubung ke platform interoperabilitas Kemenkes | Integrasi SATUSEHAT (P2-01); model data memakai kode standar sejak Fase 1 |
| Pasal 14 ayat 2 | Data identitas minimum: nomor RM, nama, NIK | NIK diisi saat pendaftaran; pengecualian dicatat |
| Pasal 16 | Catatan klinis lengkap, jelas, memuat nama, waktu, dan tanda tangan tenaga kesehatan | Tiap catatan menyimpan pengguna dan cap waktu; tanda tangan elektronik bersifat opsional (Pasal 31) |
| Pasal 29 dan 30 | Kerahasiaan, integritas, ketersediaan; hak akses input, perbaikan, dan lihat; perbaikan data maksimal 2x24 jam sejak input | RBAC, audit log, aturan kunci catatan (RM-07) |
| Pasal 33 sampai 37 | Pembukaan isi rekam medis kepada pihak lain dibatasi, dengan atau tanpa persetujuan pasien sesuai syarat | Ekspor dan cetak rekam medis hanya oleh peran tertentu, dengan alasan, dan tercatat |
| Pasal 39 | Penyimpanan data rekam medis elektronik paling singkat 25 tahun sejak kunjungan terakhir | NFR-14 |
| Pasal 42 | Pelanggaran dapat dikenai teguran tertulis dan rekomendasi pencabutan atau pencabutan status akreditasi | Kepatuhan dipantau sebagai risiko (bagian 19) |
| UU 27/2022 tentang Pelindungan Data Pribadi | Data kesehatan termasuk data pribadi spesifik; perlu dasar pemrosesan, pengamanan, hak subjek data, dan penanganan kebocoran | PAS-05, enkripsi kolom sensitif, prosedur insiden |

Sebelum go-live, konfirmasikan ke dinas kesehatan atau konsultan hukum: kewajiban registrasi PSE dan SATUSEHAT untuk klinik ini, rincian standar data SATUSEHAT terbaru, dan kewajiban UU PDP seperti batas waktu pemberitahuan kebocoran data.

**Kontrol keamanan yang dibangun**

- **Autentikasi:** hash kata sandi modern, pembatasan percobaan login, kata sandi minimal 10 karakter, 2FA opsional, sesi berakhir saat tidak aktif.
- **Otorisasi:** Policy per aksi di server; menu yang disembunyikan di UI bukan satu-satunya penjaga. Semua query dibatasi per klinik atau cabang lewat global scope yang diuji.
- **Identifikasi aman:** ID di URL memakai ULID atau UUID, bukan angka berurutan.
- **Enkripsi:** HTTPS wajib dengan HSTS; kolom sensitif (NIK, nomor HP) dienkripsi di aplikasi, dengan kolom hash terpisah (blind index) agar tetap bisa dicari; disk server dan cadangan dienkripsi. Pada shared hosting, disk tidak dapat dienkripsi pengguna: berkas klinis dienkripsi di aplikasi dan cadangan dienkripsi sebelum keluar dari server.
- **Hosting bersama:** `.env`, `vendor/`, dan `storage/` berada di luar akar dokumen web (atau ditolak server), `APP_DEBUG` mati, izin berkas ketat, tidak ada route web untuk migrasi atau Artisan, dan penyedia dipilih dengan lokasi data dan isolasi akun yang jelas (`docs/rules/hosting.md`).
- **Berkas unggahan:** disimpan di luar folder publik, diakses lewat URL bertanda tangan yang kedaluwarsa, tipe dan ukuran divalidasi, nama berkas diacak.
- **Jejak akses:** selain perubahan, pembukaan rekam medis pasien (siapa, kapan) juga dicatat dan dapat diaudit Pemilik.
- **Header dan CSRF:** CSRF bawaan Laravel, Content-Security-Policy, X-Frame-Options, dan header keamanan lain.
- **Dependensi:** `composer audit` dan `npm audit` di CI; pembaruan patch keamanan Laravel diterapkan rutin.
- **Rahasia:** `.env` tidak masuk repositori; kunci dirotasi saat staf teknis berganti.
- **Insiden:** prosedur tertulis untuk deteksi, isolasi, dan pemberitahuan kepada pasien dan otoritas sesuai UU PDP.
- **Hak pasien:** salinan resume atau rekam medis dapat dibuat sebagai PDF oleh Admin dan tercatat sebagai pembukaan isi rekam medis.
- **Pemisahan lingkungan:** data produksi tidak dipakai di lingkungan pengembangan; data uji memakai data sintetis.

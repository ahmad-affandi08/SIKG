# Aturan keamanan, privasi, dan kepatuhan

Dasar: Permenkes 24/2022 tentang Rekam Medis dan UU 27/2022 tentang Pelindungan Data Pribadi. Rekam medis adalah data pribadi spesifik (data kesehatan). Aturan ini wajib bagi setiap kode yang menyentuh data pasien.

## 1. Kewajiban kepatuhan yang memengaruhi kode

| Kewajiban | Konsekuensi di kode |
| --- | --- |
| Klinik wajib menyelenggarakan rekam medis elektronik | Sistem adalah RME utama; tidak ada alur "kertas dulu" |
| Retensi rekam medis minimal 25 tahun sejak kunjungan terakhir | Tidak ada hapus permanen lewat aplikasi (M5); cadangan sesuai `docs/operations.md` |
| Interoperabilitas dan integrasi SATUSEHAT (HL7 FHIR) | Kode standar (ICD-10, FDI, kode tindakan) dan ID konsisten sejak Fase 1; integrasi di P2-01 |
| Penyimpanan data di dalam negeri | Jangan memakai layanan penyimpanan atau galat di luar negeri untuk PHI tanpa persetujuan pengguna |
| Rekam medis bersifat rahasia | Akses sesuai peran, tercatat di `access_logs`; ekspor dan cetak hanya peran tertentu dan wajib alasan |
| Persetujuan pasien | `patient_consents` menyimpan versi teks dan waktu persetujuan |
| NIK sebagai identitas | Wajib saat registrasi; pengecualian dicatat alasannya |

## 2. Otentikasi dan sesi

- Kata sandi: hash Argon2id atau bcrypt bawaan Laravel; panjang minimal 10, tolak daftar sandi umum.
- Batas percobaan masuk 5 per menit per akun dan IP; akun terkunci sementara setelah percobaan berulang.
- Sesi kedaluwarsa karena tidak aktif (default 30 menit); sesi dibatalkan saat kata sandi diganti atau akun dinonaktifkan.
- Pemilik dan Admin: autentikasi dua faktor (TOTP) tersedia sebagai opsi (AUTH-05, Should). Jangan menjadikannya wajib tanpa persetujuan pengguna.
- Tidak ada akun bersama. Setiap tindakan klinis atau keuangan dikaitkan dengan satu pengguna.
- Reset kata sandi lewat tautan sekali pakai berkedaluwarsa; pesan galat tidak membocorkan keberadaan akun.

## 3. Otorisasi

- Dilakukan di server untuk setiap route dan setiap aksi (M9). Gunakan Policy atau permission; tes otorisasi menguji tiap sel matriks peran.
- Cegah IDOR: semua query ter-scope klinik dan cabang dan dicek terhadap pengguna. Tes memastikan pengguna klinik A tidak dapat membaca data klinik B.
- Aksi sensitif memerlukan izin sendiri dan alasan: `invoice.void`, `payment.refund`, `encounter.correct`, `record.export`, `record.print`, `stock.override`, `user.manage`.
- Perilaku default aman: bila izin tidak terdefinisi, tolak.

## 4. Perlindungan data

- Enkripsi dalam perjalanan: HTTPS dan HSTS di produksi.
- Enkripsi di aplikasi untuk NIK dan nomor HP (kolom `*_encrypted`), blind index HMAC untuk pencarian. Kunci di variabel lingkungan atau secret manager, bukan di repositori. Rencana rotasi kunci didokumentasikan di `docs/operations.md`.
- Enkripsi cadangan. Cadangan diuji dipulihkan secara berkala.
- Berkas unggahan (rontgen, foto): disk privat, bukan `public`; diakses lewat URL bertanda tangan berkedaluwarsa singkat; validasi MIME dan ukuran; nama berkas diacak; tidak ada eksekusi dari folder unggahan.
- Header: CSP ketat, `X-Content-Type-Options`, `Referrer-Policy: same-origin`, `X-Frame-Options: DENY`.
- Cookie sesi: `Secure`, `HttpOnly`, `SameSite=Lax`. CSRF aktif di semua route web dan `/internal-api`.
- Endpoint JSON hanya di `/internal-api` dengan cookie sesi dan CSRF. Jangan membuat token publik atau API terbuka tanpa ADR.

## 5. Yang dilarang (PHI)

- PHI di URL atau query string (gunakan ID dan POST).
- PHI di log aplikasi, pesan pengecualian, pelacak galat (Sentry harus punya scrubbing: nama, NIK, telepon, alamat, isi catatan), `console`, atau judul halaman yang masuk riwayat peramban.
- PHI di data uji, fixture, seed, tangkapan layar, atau contoh dokumentasi. Pakai data sintetis (Faker lokal id_ID) dan NIK uji berformat valid tetapi fiktif.
- Menyalin data produksi ke lingkungan pengembangan. Bila butuh, harus dianonimkan dengan skrip yang disetujui pengguna.
- Mengirim PHI ke layanan pihak ketiga (analitik, AI, surel, pesan) tanpa persetujuan pengguna.

## 6. Audit dan akses

- `audit_logs`: setiap perubahan data pasien, rekam medis, tagihan, pembayaran, pengguna, peran, dan master harga. Memuat aktor, aksi, subjek, sebelum, sesudah, IP, waktu.
- `access_logs`: setiap pembukaan rekam medis pasien, ekspor, cetak, dan unduh berkas.
- Log tidak bisa diubah atau dihapus dari aplikasi; hanya INSERT.
- Laporan "siapa mengakses pasien X" tersedia untuk Pemilik.
- Rekam medis yang ditutup tidak bisa dihapus; koreksi lewat addendum dengan penulis dan waktu (RM-07).

## 7. Input dan keluaran

- Validasi di server untuk semua input. Query lewat Eloquent atau binding parameter; tidak ada penggabungan string SQL.
- Keluaran React di-escape secara default. Dilarang `dangerouslySetInnerHTML` kecuali konten sudah disanitasi dan ada komentar alasan.
- Unggahan Excel (impor) divalidasi per baris dan dicatat di `import_batches`; hasil galat bisa diunduh tanpa memuat PHI berlebih.
- Rate limit: pencarian 60 per menit per pengguna; endpoint sensitif lebih ketat.

## 8. Rantai pasok

- `composer audit` dan `npm audit` dijalankan di CI; kerentanan tinggi atau kritis menggagalkan build.
- Kunci versi (`composer.lock`, `package-lock.json`) di-commit. Dependensi baru memerlukan alasan dan persetujuan (M1).
- Tidak ada skrip CDN eksternal di produksi; aset dibundel oleh Vite.

## 9. Penanganan insiden

- Bila menemukan PHI nyata, rahasia, atau kunci di repositori atau log: **berhenti**, jangan menyalinnya ke tanggapan, laporkan ke pengguna secara ringkas (lokasi, bukan isi), dan sarankan rotasi.
- Prosedur pelaporan insiden pelindungan data klinik ada di `docs/operations.md`.

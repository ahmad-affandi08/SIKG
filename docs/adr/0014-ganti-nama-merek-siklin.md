# ADR-0014: Nama merek SIKLIN menggantikan SIKG (nama tampilan saja)

- Status: Disetujui (permintaan pengguna: "SIKG" terlalu terikat ke klinik gigi, sedangkan aplikasi akan melayani klinik umum dan rawat inap)
- Konteks terkait: M18 (bahasa dan format), M16 (berkas terlindungi), ADR-0006 (profil hosting), ADR-0013 (WhatsApp)

## Konteks

Nama kerja SIKG (Sistem Informasi Klinik Gigi) menyiratkan klinik gigi. Calon pengguna berikutnya adalah klinik umum dan klinik pratama rawat inap. Pengguna memilih nama **SIKLIN** (Sistem Informasi Klinik). Pencarian web dan pemeriksaan domain pada saat keputusan tidak menemukan produk, perusahaan, atau domain aktif dengan nama itu; pemeriksaan merek resmi (DJKI, kelas 9 dan 42) dan domain `.id` tetap menjadi tanggung jawab pemilik produk.

## Keputusan

- **Yang diganti: semua nama yang terlihat pengguna.** Antarmuka (logo menubar, judul tab, halaman masuk dan verifikasi dua langkah), aplikasi terpasang dan halaman offline (`public/manifest.webmanifest`, `public/offline.html`), cetakan PDF dan berkas Excel (pembuat dan deskripsi berkas), pesan WhatsApp ke staf dan pesan uji, nama penerbit pada aplikasi autentikator (TOTP), awalan nomor tagihan online di penyedia pembayaran (`SIKLIN-<id>`), pesan galat cadangan, brosur, dan panduan di `docs/` yang tidak terlindungi.
- **Satu sumber nama.** Backend: `Src\Shared\Domain\Brand` (`NAME`, `TAGLINE`). Frontend: `resources/js/lib/brand.ts`. Berkas statis di `public/` tidak dapat mengimpor keduanya dan diubah bersamaan; penggantian nama berikutnya cukup mengubah tiga tempat itu.
- **Yang sengaja tidak diganti (identitas teknis):** perintah `sikg:*` (sudah tertulis di cron klien), variabel `SIKG_*`, `config/sikg.php` dan kuncinya, kunci cache, sesi, dan penyimpanan peramban, format berkas cadangan `.sikgbak` beserta penanda `SIKGBAK1`, serta nama repositori. Mengganti semuanya merusak instalasi yang berjalan dan kemampuan memulihkan cadangan lama tanpa memberi manfaat bagi pengguna. Bila kelak dianggap perlu, itu keputusan terpisah dengan jalur migrasi (alias perintah, pembacaan dua awalan, dan penanda cadangan lama tetap dikenali).
- **Logo.** Huruf "S" pada ikon aplikasi tetap dipakai karena SIKLIN juga berawalan S.

## Akibat

- Berkas terlindungi yang masih menyebut SIKG dan "Sistem Informasi Klinik Gigi" (`CLAUDE.md`, `docs/PRD/`, `docs/rules/`, `docs/operations.md`, `docs/BOOTSTRAP.md`, `scripts/`, `tooling/`, `.claude/`, `.github/`) tidak diubah oleh Claude Code. Pengguna memperbaruinya sendiri bila diinginkan, lalu menyegel ulang.
- Akun autentikator yang sudah terdaftar tetap berlabel "SIKG" di aplikasi autentikator pengguna sampai didaftarkan ulang; rahasia dan kode tidak berubah.
- Tagihan online yang sudah dibuat sebelum perubahan tetap memakai awalan `SIKG-`; pencocokan webhook memakai nomor yang tersimpan sehingga tidak terpengaruh.
- Judul `README.md` ("SIKG Development Kit") menamai kit pengembangan dan tidak diubah.

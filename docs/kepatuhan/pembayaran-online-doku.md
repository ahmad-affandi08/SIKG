# Daftar periksa go-live pembayaran online (DOKU)

Berlaku untuk P3-05 (ADR-0012, Q-29). Kode memakai respons palsu pada tes; uji terhadap DOKU nyata wajib dilakukan klinik sebelum produksi.

## Persiapan akun DOKU

1. Daftar merchant DOKU dan selesaikan verifikasi usaha.
2. Di DOKU Back Office, aktifkan metode QRIS untuk Checkout.
3. Ambil Client ID dan Secret Key (sandbox lebih dulu, produksi kemudian). Rahasia ini hanya dimasukkan lewat halaman Pengaturan pembayaran online; jangan dikirim lewat pesan atau disimpan di berkas.

## Pengaturan di SIKG

1. Buka Pengaturan, Pembayaran online. Isi Client ID dan Secret Key, pilih lingkungan Sandbox, pilih metode pembayaran penerimaan, lalu simpan.
2. Salin alamat notifikasi (`/webhooks/payments/{token}` lengkap dengan domain klinik) dan daftarkan sebagai Notification URL di DOKU Back Office. Alamat harus HTTPS.
3. Aktifkan fitur. Aktivasi ditolak bila Client ID, Secret Key, atau metode belum lengkap.

## Uji sandbox (wajib)

- Buat pembayaran dari sebuah tagihan uji, buka halaman QRIS, bayar lewat simulator DOKU; pastikan pembayaran tercatat satu kali pada tagihan dan tagihan lunas.
- Ulangi notifikasi dari DOKU Back Office; pastikan tidak ada pembayaran ganda.
- Buat pembayaran lalu biarkan kedaluwarsa; pastikan niat berstatus kedaluwarsa dan tagihan tetap terbuka.
- Jalankan `php artisan sikg:payments:reconcile` (atau tunggu cron) dengan notifikasi dimatikan; pastikan pembayaran tetap tercatat.
- Bayar sebagian jumlah berbeda atau bayar setelah tagihan lunas tunai; pastikan niat berstatus Mismatch dan petugas melihatnya.

## Pindah ke produksi

1. Ganti ke Client ID dan Secret Key produksi, pilih lingkungan Produksi, daftarkan ulang Notification URL di akun produksi.
2. Lakukan satu transaksi nominal kecil dan pastikan tercatat; refund dilakukan di dasbor DOKU lalu dicatat sebagai refund biasa di SIKG.
3. Pastikan cron (`schedule:run` dan antrean) berjalan, karena rekonsiliasi bergantung padanya.

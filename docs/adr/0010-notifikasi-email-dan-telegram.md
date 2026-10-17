# ADR-0010: Notifikasi untuk pemilik dan staf lewat email dan Telegram, tanpa WhatsApp dan tanpa menghubungi pasien

- Status: Disetujui (permintaan pengguna)
- Konteks terkait: SYS-05, SYS-04, OBT-06, JDW-07, AUTH-05; M11, M13, M20, M21

## Konteks

PRD menyebut pengingat WhatsApp (JDW-07). WhatsApp berbayar dan pasien tidak perlu memakai kanal lain milik klinik. Pengguna meminta semua pemberitahuan memakai email atau Telegram, dan hanya masuk ke pemilik dan staf klinik. Nomor HP pasien tetap disimpan sebagai data kontak pasien (PAS-01) tetapi tidak dipakai untuk mengirim pesan otomatis.

## Keputusan

- Pemberitahuan diterbitkan lebih dulu sebagai notifikasi dalam aplikasi (SYS-05) untuk pemegang izin tertentu. Hanya saat pertama kali terbit (dedupe per kunci), pesan diteruskan ke satu email dan satu grup Telegram klinik yang diatur Admin di pengaturan klinik (SYS-04).
- Telegram memakai Bot API lewat HTTPS dengan klien HTTP Laravel, tanpa pustaka baru (M1). Token bot hanya dari lingkungan (`TELEGRAM_BOT_TOKEN`, M13), tidak disimpan di basis data dan tidak masuk log. Email memakai konfigurasi surel Laravel.
- Isi pesan tidak memuat nama pasien, diagnosis, atau data klinis (M11) karena dapat diteruskan ke grup.
- Pemicu: janji baru; stok di bawah minimum dan obat yang kedaluwarsa dalam 30 hari (sekali per hari); ringkasan janji hari ini (mulai 07.00) dan besok (mulai 09.00) berupa jumlah dan jam pertama (sekali per hari).
- Semua digerakkan cron dan tahan cron yang terlewat (M21): perintah dijalankan berkala dan idempoten lewat kunci dedupe, tanpa daemon, webhook, atau antrean khusus.
- Pasien tidak menerima pesan otomatis dalam bentuk apa pun. Tidak ada tabel kontak pasien (email atau Telegram) dan tidak ada penautan akun Telegram pasien.

## Akibat

- Biaya pesan nol selain langganan surel klinik sendiri.
- Staf menghubungi pasien secara manual (telepon atau WhatsApp pribadi) dengan melihat jadwal; aplikasi tidak mengirimkannya.
- JDW-07 dipenuhi sebagai ringkasan janji untuk pemilik dan staf, bukan pengingat ke pasien. Bila kelak pasien diminta menerima pesan otomatis, itu keputusan baru yang memerlukan persetujuan pasien (opt-in), ADR baru, dan penyesuaian M11.

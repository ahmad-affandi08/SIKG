# ADR-0011: Integrasi eksternal Fase 2: SATUSEHAT dan pengingat pasien lewat WhatsApp

- Status: Disetujui (permintaan pengguna: kerjakan P2-01 sampai P2-09)
- Konteks terkait: P2-01, P2-03, ADR-0005, ADR-0010; M1, M11, M13, M20, M21

## Konteks

Fase 2 memuat dua integrasi keluar: pengiriman kunjungan ke SATUSEHAT (P2-01) dan pengingat WhatsApp ke pasien (P2-03). ADR-0010 sebelumnya memutuskan pemberitahuan hanya untuk pemilik dan staf lewat email dan Telegram, tanpa menghubungi pasien secara otomatis, karena WhatsApp berbayar. P2-03 menuntut pengingat ke pasien, sehingga perlu keputusan yang menyelaraskan keduanya. Nomor HP pasien tetap data kontak pasien (PAS-01).

## Keputusan

- **SATUSEHAT** dikerjakan sebagai konteks `Integration` (lapisan anti-korupsi, ADR-0005) memakai klien HTTP Laravel, tanpa pustaka baru (M1). Kunjungan yang ditutup dikirim sebagai satu Bundle transaksi FHIR R4 (Encounter, Condition ICD-10, Procedure ICD-9-CM bila katalog memiliki kodenya). Antrean `satusehat_submissions` digerakkan cron dengan jeda percobaan ulang bertahap (M21); pesan galat dibersihkan dari data pasien. Client ID dan client secret disimpan terenkripsi di aplikasi, tidak pernah ditampilkan kembali dan tidak masuk log (M13).
- **Pengingat pasien memakai dua mode, bawaan gratis.** Mode `link` (bawaan): sistem menyiapkan pengingat dan petugas membuka WhatsApp dengan satu klik; tidak ada biaya pesan dan tidak ada pengiriman otomatis. Mode `api` (opsional, diaktifkan klinik): pengingat dikirim otomatis lewat WhatsApp Business API milik klinik sendiri (Meta Cloud API dengan templat yang sudah disetujui); biayanya sesuai penyedia dan ditanggung klinik. Token API hanya dari pengaturan terenkripsi dan tidak masuk log.
- **Perlindungan data pasien tetap berlaku (M11).** Nomor HP tidak disalin ke tabel pengingat dan tidak muncul di URL atau log; ia hanya diambil saat petugas menekan tombol kirim dan pembukaannya tercatat di access log. Isi pesan tidak disimpan. Pasien yang menolak pengingat dicatat (opt-out) dan dilewati. Pesan hanya memuat nama depan, tanggal, jam, dokter, dan nama klinik.
- **ADR-0010 tetap berlaku untuk notifikasi staf** (stok, ringkasan janji, verifikasi dua langkah): hanya email dan Telegram untuk pemilik dan staf. Bagian ADR-0010 yang menyatakan "pasien tidak menerima pesan otomatis" digantikan oleh keputusan ini: pasien dapat dihubungi lewat WhatsApp hanya melalui pengingat P2-03, dengan klik petugas (mode `link`) atau dengan pengiriman otomatis yang sengaja diaktifkan klinik (mode `api`). Tidak ada kontak email atau Telegram pasien.
- Jadwal pengingat: H-1 mulai 09.00 dan hari-H mulai 07.00 waktu klinik, kontrol berkala pada bulan ke-N sejak kunjungan terakhir dengan jendela 30 hari. Semua idempoten per janji atau kunjungan dan tahan cron yang terlewat.

## Akibat

- Klinik yang tidak mengisi kredensial tidak menanggung biaya apa pun dan tetap mendapat daftar pengingat yang dapat dikirim manual.
- Mengaktifkan mode `api` adalah keputusan bisnis klinik dan memerlukan akun WhatsApp Business serta templat yang disetujui; dokumen ini bukan nasihat hukum, dan persetujuan pasien menerima pesan mengikuti kebijakan klinik.
- Dokumen registrasi sistem elektronik dan langkah mengaktifkan SATUSEHAT ada di `docs/kepatuhan/registrasi-pse-dan-satusehat.md`.

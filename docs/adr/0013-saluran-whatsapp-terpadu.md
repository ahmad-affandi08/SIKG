# ADR-0013: Saluran WhatsApp terpadu menggantikan email dan Telegram

- Status: Disetujui (permintaan pengguna: hapus integrasi email dan Telegram, pakai WhatsApp saja, diatur di UI dengan pilihan WhatsApp Resmi dan penyedia tidak resmi seperti Fonnte)
- Menggantikan sebagian: ADR-0010 (notifikasi staf lewat email dan Telegram) dan bagian "notifikasi staf" dari ADR-0011
- Konteks terkait: SYS-04, SYS-05, JDW-07, P2-03, P3-01; M1, M11, M13, M20, M21

## Konteks

Pemberitahuan staf (SYS-05, JDW-07, peringatan kesehatan dan cadangan SYS-02/SYS-07) memakai email dan bot Telegram. Pengingat pasien (P2-03) dan kode verifikasi booking (P3-01) sudah memakai WhatsApp, tetapi hanya lewat WhatsApp Business Cloud API milik Meta. Pengguna meminta semua saluran email dan Telegram dihapus, dan memakai WhatsApp saja untuk seluruh pengiriman, dengan pilihan penyedia di antarmuka: WhatsApp Resmi dan beberapa penyedia tidak resmi.

## Keputusan

- **Email dan Telegram dihapus** sebagai saluran pengiriman: `EmailSender`, `TelegramSender`, kelas surel peringatan, templat surel, kunci `telegram_bot_token`, `telegram_bot_username`, dan `alert_email` di `config/sikg.php`. Email pengguna tetap ada sebagai identitas masuk dan label autentikator (AUTH-01, AUTH-05); itu bukan integrasi pengiriman. Email kontak pemasok, laboratorium, dan penjamin tetap sebagai data master.
- **Satu saluran WhatsApp per klinik**, dipilih Admin di Pengaturan, WhatsApp. Penyedia: `link` (manual, gratis, bawaan), `meta` (WhatsApp Resmi, Cloud API), `fonnte`, `wablas`, dan `waha` (tidak resmi). Saluran yang sama melayani tiga hal: pengingat pasien (P2-03), kode verifikasi booking (P3-01), dan pemberitahuan staf (SYS-05).
- **Resmi memakai templat, tidak resmi memakai teks biasa.** `WhatsAppMessenger` memilih sesuai penyedia: Meta mengirim templat yang disetujui (nama templat pengingat per jenis, templat kode booking, dan satu templat pemberitahuan staf dengan dua parameter judul dan isi); penyedia tidak resmi mengirim teks yang sama dengan yang dilihat petugas pada mode tautan. Tanpa templat staf, WhatsApp Resmi tidak meneruskan pemberitahuan staf (tetap tampil di aplikasi).
- **Tujuan staf** adalah satu sampai lima nomor WhatsApp di Pengaturan klinik (`notify_whatsapp`), dinormalkan ke 628... Peringatan sistem (kesehatan, cadangan) yang bersifat global diterbitkan per klinik ke pemegang izin `setting.manage` dan diteruskan ke nomor staf klinik itu.
- **Kredensial** disimpan terenkripsi di aplikasi seperti kredensial SATUSEHAT, tidak pernah dikirim balik ke layar, dan tidak masuk log atau audit (M13). Penyimpanan memakai kolom `reminder_settings` yang ada ditambah `provider`, `api_base_url`, `api_session`, dan `staff_template` (migrasi 000206, tanpa menghapus kolom lama; pengaturan yang memakai `mode = api` otomatis menjadi `meta`).
- **Alamat server yang diisi pengguna** (Wablas, WAHA) dijaga dari SSRF: hanya HTTPS dan nama server RFC 1123 (atau IPv4 titik-desimal publik; literal IPv6 dan IPv4 tidak kanonik ditolak), bukan nama internal; saat mengirim, nama server diselesaikan (A dan AAAA), semua alamatnya harus publik (pembungkus IPv4 dalam IPv6, multicast, dan rentang khusus ditolak), dan koneksi dikunci ke alamat yang baru diperiksa (`CURLOPT_RESOLVE`) agar DNS tidak dapat berubah sebelum koneksi; tanpa pengalihan HTTP. Satu pesan galat dipakai untuk semua penolakan agar tidak menjadi alat memindai jaringan dalam. Pesan galat dari penyedia tidak dikutip karena dapat memuat nomor atau token.
- **Token milik satu penyedia dan satu alamat.** Mengganti penyedia atau alamat server tanpa mengisi token baru tidak membawa token lama: bila penyedia baru mewajibkan token, penyimpanan ditolak; bila token opsional atau penyedia berupa tautan manual, token lama dihapus. Token hanya berisi karakter tercetak tanpa spasi.
- **Pesan uji hanya ke nomor staf klinik** (Pengaturan klinik, Notifikasi) dan dibatasi 10 permintaan per menit, agar fitur ini tidak dapat dipakai mengirim pesan ke sembarang nomor. Audit mencatat aktor, hasil, dan nomor bertopeng; audit pengaturan mencatat isian bukan rahasia sebelum dan sesudah (termasuk alamat server) dan tidak pernah token.
- Tanpa pustaka baru (M1): semua klien memakai klien HTTP Laravel; berjalan di shared hosting (M21) dan digerakkan cron.

## Akibat

- **Penyedia tidak resmi melanggar ketentuan layanan WhatsApp.** Nomor klinik dapat diblokir sewaktu-waktu, tidak ada jaminan layanan, dan isi pesan melewati server pihak ketiga. Pesan SIKG tidak memuat diagnosa atau NIK, tetapi pengingat memuat nama depan, tanggal, jam, dan nama dokter. Antarmuka menampilkan peringatan ini saat penyedia tidak resmi dipilih; pilihan menjadi tanggung jawab klinik. Untuk kepatuhan penuh pakai WhatsApp Resmi.
- Kode verifikasi booking (P3-01) lewat penyedia tidak resmi hanya setegar nomor klinik itu: bila nomor diblokir, verifikasi berhenti dan booking menunggu konfirmasi staf.
- Pengiriman pemberitahuan staf berjalan sinkron seperti saluran sebelumnya (maksimal lima nomor per klinik); memindahkannya ke antrean `database` dapat dipertimbangkan bila penyedia terasa lambat.
- Klinik tanpa saluran otomatis tetap berfungsi: pemberitahuan tampil di aplikasi, pengingat pasien dikirim manual lewat tautan, dan booking menunggu konfirmasi staf.
- Integrasi Fonnte, Wablas, dan WAHA diuji dengan simulasi HTTP, belum dengan akun nyata. Bentuk permintaan mengikuti dokumentasi publik masing-masing penyedia pada saat penulisan; uji dengan tombol "Kirim pesan uji" sebelum dipakai.
- Menambah penyedia baru cukup menambah satu kelas klien dan satu nilai pada `WhatsAppProvider`.

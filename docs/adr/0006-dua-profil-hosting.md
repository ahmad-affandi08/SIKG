# ADR-0006: Dua profil hosting (server dan shared hosting)

- Status: Disetujui (atas permintaan pengguna agar sistem kompatibel dengan shared hosting; rincian teknis di bawah dapat ditinjau ulang)
- Konteks terkait: seluruh sistem; NFR-03, NFR-10, NFR-13, NFR-16; SYS-02, SYS-06, SYS-07

## Konteks

PRD awal mengasumsikan satu VPS dengan Nginx, Redis, dan Supervisor. Banyak klinik kecil hanya mampu atau hanya mau memakai shared hosting (cPanel atau sejenis). Di sana tidak ada akses root, tidak ada Supervisor atau Redis, tidak ada Node, symlink dan eksekusi perintah sistem sering dibatasi, cron menjadi satu-satunya penjadwal, dan MySQL tidak menyediakan hak `SUPER`/`TRIGGER` maupun binlog. Data yang disimpan adalah data kesehatan, sehingga kompatibilitas tidak boleh dicapai dengan melonggarkan keamanan.

## Keputusan

SIKG mendukung dua profil hosting dengan kode yang sama; profil hanya mengubah konfigurasi dan prosedur rilis:

- **Profil A (server):** VPS atau cloud, Nginx + PHP-FPM, Supervisor, Redis opsional, rilis berbasis symlink.
- **Profil B (shared hosting):** Apache/LiteSpeed + PHP 8.3+ dari panel, MySQL dari panel, cron dari panel, tanpa daemon.

Semua kode mengikuti `docs/rules/hosting.md` (CLAUDE.md M21). Ringkasannya: driver `database` sebagai bawaan; antrean dijalankan `queue:work --stop-when-empty` oleh cron; satu cron `schedule:run`; polling bukan WebSocket; tanpa `exec`/`proc_open`; PDF memakai dompdf; build aset di CI; tanpa symlink; tanpa trigger atau stored procedure; kunci konkurensi lewat kunci baris MySQL. Berkas klinis dienkripsi di aplikasi pada Profil B. Dua kebutuhan baru ditambahkan: SYS-06 (pemeriksaan kesiapan lingkungan) dan SYS-07 (denyut cron dan antrean).

## Alternatif yang dipertimbangkan

- **Hanya VPS (status quo):** paling sederhana, tetapi menutup klinik yang tidak mampu mengelola VPS.
- **Hanya shared hosting:** menurunkan semua keputusan ke batas terendah dan menutup jalan naik; Redis, Pulse, dan binlog tetap berguna pada skala lebih besar.
- **Fork kode per profil:** biaya pemeliharaan ganda dan risiko kedua cabang berbeda perilaku. Ditolak.
- **Route web untuk migrasi dan Artisan agar tanpa SSH:** permukaan serangan terlalu besar untuk aplikasi data kesehatan. Ditolak; tanpa SSH migrasi lewat cron sekali jalan.
- **`spatie/laravel-pdf` (Chromium):** butuh biner dan proses yang tidak ada di hosting bersama. Dompdf dipilih untuk kedua profil agar hasil cetak sama.

## Akibat

- Lebih mudah: klinik dapat mulai di shared hosting dan pindah ke VPS tanpa mengubah kode (ubah `.env`, pasang Supervisor, aktifkan Redis bila perlu).
- Lebih sulit: tidak ada waktu nyata; antrean tertunda sampai satu interval cron; job harus pendek, idempoten, dan berpotongan; PDF dibatasi kemampuan dompdf; layout cetak diuji di dompdf.
- Kelonggaran yang harus disetujui pemilik pada Profil B (Q-1, Q-3): RPO mengikuti interval dump (tanpa binlog), ketersediaan mengikuti SLA penyedia, performa wajib diuji pada hosting sasaran.
- Risiko baru R13 (lingkungan bersama) dicatat di PRD bagian 19.
- Pengaman: job CI "hosting bersama" (tanpa Redis, `disable_functions`), arch test, aturan `guard.py` `HOST` (usulan), SYS-06 dan SYS-07 di aplikasi.

Catatan: ADR tidak memuat tanggal target atau estimasi durasi.

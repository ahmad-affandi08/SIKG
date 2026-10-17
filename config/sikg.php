<?php

declare(strict_types=1);

/*
 * Konfigurasi khusus SIKG. `env()` hanya dipanggil dari config/ agar `config:cache` benar (docs/rules/hosting.md butir 12).
 */
return [
    // 'shared' (shared hosting) atau 'server' (VPS). Hanya mengubah bawaan operasional, bukan aturan domain.
    'hosting_profile' => env('SIKG_HOSTING_PROFILE', 'shared'),

    // Berkas klinis dienkripsi di aplikasi bila disk tidak dienkripsi pengguna (docs/rules/security-privacy.md).
    'encrypt_files' => (bool) env('SIKG_ENCRYPT_FILES', true),

    // Zona waktu klinik bawaan; nilai sebenarnya dari pengaturan klinik (SYS-04). Aplikasi tetap UTC.
    'clinic_timezone' => env('CLINIC_TIMEZONE', 'Asia/Jakarta'),

    // Token endpoint /health (SYS-07). Kosong berarti endpoint menolak semua permintaan.
    'health_token' => env('HEALTH_TOKEN'),

    // Ukuran potongan impor/ekspor dan laporan berat agar job muat dalam batas waktu hosting.
    'chunk_size' => (int) env('SIKG_CHUNK_SIZE', 500),

    // Jumlah baris data maksimal per berkas impor pasien (PAS-08); berkas lebih besar dipecah oleh pengguna.
    'import_max_rows' => (int) env('SIKG_IMPORT_MAX_ROWS', 5000),

    // Batas sesi tidak aktif bawaan (menit) dan batas tertinggi yang boleh dipilih klinik (AUTH-04).
    // Sesi framework hidup selama batas tertinggi; penegakan batas sebenarnya oleh middleware EnforceIdleTimeout.
    'session_default_minutes' => (int) env('SESSION_LIFETIME', 30),
    'session_max_minutes' => (int) env('SIKG_SESSION_MAX_MINUTES', 480),

    // Denyut cron/antrean dianggap terlambat setelah sekian detik (SYS-07). Naikkan bila cron hosting minimal 5 menit.
    'heartbeat_stale_seconds' => (int) env('SIKG_HEARTBEAT_STALE_SECONDS', 600),

    // Surel penanggung jawab teknis untuk peringatan kesehatan (kosong = hanya dicatat di log).
    'alert_email' => env('SIKG_ALERT_EMAIL'),

    // SYS-02: cadangan. Disk tujuan (bawaan lokal di storage/app/backups; arahkan ke disk lain bila tersedia), interval jatuh tempo
    // dalam jam (usulan 6 pada shared hosting tanpa binlog; 24 pada server), dan kunci enkripsi cadangan terpisah dari APP_KEY.
    'backup_disk' => env('SIKG_BACKUP_DISK', 'backup'),
    'backup_interval_hours' => (int) env('SIKG_BACKUP_INTERVAL_HOURS', 24),
    'backup_key' => env('BACKUP_KEY'),

    // Kunci HMAC blind index NIK dan nomor HP (M11).
    'pii_hash_key' => env('PII_HASH_KEY'),
];

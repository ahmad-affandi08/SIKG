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

    // Kunci HMAC blind index NIK dan nomor HP (M11).
    'pii_hash_key' => env('PII_HASH_KEY'),
];

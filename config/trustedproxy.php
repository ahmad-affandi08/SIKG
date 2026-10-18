<?php

declare(strict_types=1);

// Proxy yang dipercaya untuk header X-Forwarded-* (alamat IP klien, HTTPS). Bawaan: hanya proxy lokal/jaringan privat
// (Nginx/Caddy di server yang sama). Header dari alamat lain diabaikan, sehingga IP untuk pembatasan laju dan jejak audit
// tidak dapat dipalsukan klien. Bila ada proxy publik di depan (mis. Cloudflare), isi TRUSTED_PROXIES dengan daftar IP/CIDR
// proxy itu dipisah koma.
return [
    'proxies' => ($configured = trim((string) env('TRUSTED_PROXIES', ''))) !== ''
        ? array_map('trim', explode(',', $configured))
        : ['127.0.0.1', '::1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', 'fc00::/7'],
];

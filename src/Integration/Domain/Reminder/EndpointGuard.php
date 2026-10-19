<?php

declare(strict_types=1);

namespace Src\Integration\Domain\Reminder;

/**
 * Menjaga alamat dasar API yang diisi pengguna (Wablas, WAHA) agar server tidak dipakai menjangkau jaringan dalam (SSRF):
 * hanya HTTPS, tanpa kredensial di alamat, bukan localhost atau nama internal, dan bila berupa alamat IP maka harus publik.
 * Pemeriksaan DNS saat pengiriman dilakukan di lapisan infrastruktur.
 */
final class EndpointGuard
{
    private const INTERNAL_SUFFIXES = ['.local', '.localhost', '.internal', '.localdomain', '.lan', '.home', '.corp'];

    /**
     * @return string alamat dasar yang sudah dirapikan (tanpa garis miring penutup)
     *
     * @throws InvalidReminder
     */
    public static function normalize(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);
        if ($parts === false || ($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') === '') {
            throw new InvalidReminder('Alamat server harus diawali https:// dan memuat nama server.');
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidReminder('Alamat server tidak boleh memuat kata sandi, parameter, atau fragmen.');
        }
        $host = strtolower(trim($parts['host'], '[]'));
        if (! self::publicHost($host)) {
            throw new InvalidReminder('Alamat server harus berupa nama server publik, bukan alamat jaringan dalam.');
        }
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return 'https://'.$host.$port.rtrim($parts['path'] ?? '', '/');
    }

    /**
     * Rentang IPv4 khusus yang tidak ditangkap bawaan PHP (ruang bersama CGNAT, dokumentasi, uji kinerja, multicast).
     */
    private const SPECIAL_V4 = ['100.64.0.0/10', '192.0.0.0/24', '192.0.2.0/24', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4'];

    /** Dipakai juga setelah DNS diselesaikan: alamat IP hasilnya (IPv4 atau IPv6) tidak boleh privat, khusus, atau membungkus alamat tersebut. */
    public static function publicIp(string $ip): bool
    {
        $ip = strtolower(trim($ip, '[]'));
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return false;
        }
        if (strlen($packed) === 16) {
            $embedded = self::embeddedV4($packed);
            if ($embedded !== null) {
                return self::publicIp($embedded);
            }
            // Multicast (ff00::/8) dan segmen khusus lain; privat/link-local/loopback ditangkap bawaan PHP di bawah.
            if (ord($packed[0]) === 0xFF) {
                return false;
            }
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
        if (strlen($packed) === 4) {
            $long = (int) sprintf('%u', ip2long($ip));
            foreach (self::SPECIAL_V4 as $cidr) {
                [$net, $bits] = explode('/', $cidr);
                $mask = (-1 << (32 - (int) $bits)) & 0xFFFFFFFF;
                if (($long & $mask) === ((int) sprintf('%u', ip2long($net)) & $mask)) {
                    return false;
                }
            }
        }

        return true;
    }

    /** IPv4 yang dibungkus IPv6 (::ffff:a.b.c.d, ::a.b.c.d, 64:ff9b::/96, 2002::/16), atau null bila bukan pembungkus. */
    private static function embeddedV4(string $packed): ?string
    {
        $zeros = str_repeat("\0", 10);
        if (str_starts_with($packed, $zeros."\xff\xff") || (str_starts_with($packed, str_repeat("\0", 12)) && substr($packed, 12) !== "\0\0\0\0" && substr($packed, 12) !== "\0\0\0\x01")) {
            return (string) inet_ntop(substr($packed, 12, 4));
        }
        if (str_starts_with($packed, "\x00\x64\xff\x9b".str_repeat("\0", 8))) {
            return (string) inet_ntop(substr($packed, 12, 4));
        }
        if (str_starts_with($packed, "\x20\x02")) {
            return (string) inet_ntop(substr($packed, 2, 4));
        }

        return null;
    }

    private static function publicHost(string $host): bool
    {
        // Alamat IP harus berbentuk IPv4 titik-desimal kanonik; literal IPv6 tidak diterima (tidak ada kebutuhan nyata).
        if (preg_match('/^\d{1,3}(\.\d{1,3}){3}$/', $host) === 1) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false && self::publicIp($host);
        }
        // Nama server RFC 1123 saja (huruf, angka, strip, titik); bagian terakhir tidak boleh angka murni (cegah 127.1 atau 2130706433).
        if (preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/', $host) !== 1 || strlen($host) > 253) {
            return false;
        }
        if (preg_match('/(^|\.)(\d+|0x[0-9a-f]+)$/', $host) === 1) {
            return false;
        }
        foreach (self::INTERNAL_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return false;
            }
        }

        return $host !== 'localhost';
    }
}

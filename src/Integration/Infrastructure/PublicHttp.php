<?php

declare(strict_types=1);

namespace Src\Integration\Infrastructure;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Src\Integration\Domain\Reminder\EndpointGuard;
use Src\Integration\Domain\Reminder\InvalidReminder;
use Src\Integration\Domain\Reminder\WhatsAppFailure;

/**
 * Klien HTTP untuk alamat API yang diisi pengguna: alamat diperiksa ulang dan nama servernya diselesaikan lebih dulu, sehingga
 * server yang mengarah ke alamat jaringan dalam ditolak. Tanpa pengalihan (redirect) agar tidak dibelokkan ke jaringan dalam.
 */
final readonly class PublicHttp
{
    public function __construct(private HostResolver $resolver) {}

    /**
     * @return array{PendingRequest, string} permintaan dan alamat dasar yang aman
     *
     * @throws WhatsAppFailure
     */
    public function client(string $baseUrl, string $provider): array
    {
        try {
            $base = EndpointGuard::normalize($baseUrl);
        } catch (InvalidReminder) {
            throw new WhatsAppFailure("Server {$provider} tidak aman atau tidak dapat dijangkau. Periksa alamat server.", false);
        }
        $host = (string) parse_url($base, PHP_URL_HOST);
        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            $ips = $this->resolver->resolve($host);
            if ($ips === []) {
                throw new WhatsAppFailure("Server {$provider} tidak aman atau tidak dapat dijangkau. Periksa alamat server.", true);
            }
            foreach ($ips as $ip) {
                if (! EndpointGuard::publicIp($ip)) {
                    throw new WhatsAppFailure("Server {$provider} tidak aman atau tidak dapat dijangkau. Periksa alamat server.", false);
                }
            }
        }

        $request = Http::withoutRedirecting()->acceptJson()->timeout(20);
        // Koneksi dikunci ke alamat yang baru diperiksa, sehingga DNS tidak dapat berubah antara pemeriksaan dan koneksi (DNS rebinding).
        if (isset($ips) && defined('CURLOPT_RESOLVE')) {
            $port = (int) (parse_url($base, PHP_URL_PORT) ?: 443);
            $request = $request->withOptions(['curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:".implode(',', array_map(fn (string $ip) => str_contains($ip, ':') ? "[{$ip}]" : $ip, $ips))]]]);
        }

        return [$request, $base];
    }
}

<?php

declare(strict_types=1);

namespace Src\Integration\Infrastructure;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Src\Integration\Application\SatuSehatGateway;
use Src\Integration\Domain\SatuSehatFailure;
use Src\Integration\Domain\SatuSehatSettings;

/**
 * Klien HTTP SATUSEHAT: OAuth2 client credentials, pencarian ID pasien dan praktisi menurut NIK, dan pengiriman Bundle FHIR.
 * Tanpa pustaka tambahan (klien HTTP Laravel). Token disimpan di cache hingga sedikit sebelum kedaluwarsa. Pesan galat tidak
 * pernah memuat NIK, nama, token, atau isi respons bebas: hanya kode dan lokasi isian dari OperationOutcome.
 */
final class HttpSatuSehatGateway implements SatuSehatGateway
{
    private const NIK_SYSTEM = 'https://fhir.kemkes.go.id/id/nik';

    public function verify(SatuSehatSettings $settings): void
    {
        $this->token($settings, fresh: true);
    }

    public function findPatientIhs(SatuSehatSettings $settings, string $nik): ?string
    {
        return $this->search($settings, 'Patient', $nik);
    }

    public function findPractitionerIhs(SatuSehatSettings $settings, string $nik): ?string
    {
        return $this->search($settings, 'Practitioner', $nik);
    }

    public function submit(SatuSehatSettings $settings, array $bundle): array
    {
        $response = $this->send($settings, fn (PendingRequest $http) => $http->post($settings->environment->fhirUrl(), $bundle));
        $created = [];
        foreach ((array) $response->json('entry', []) as $entry) {
            $info = $entry['response'] ?? [];
            if (isset($info['resourceType'], $info['resourceID'])) {
                $created[] = ['type' => (string) $info['resourceType'], 'id' => (string) $info['resourceID']];
            }
        }

        return $created;
    }

    private function search(SatuSehatSettings $settings, string $type, string $nik): ?string
    {
        $response = $this->send($settings, fn (PendingRequest $http) => $http->get($settings->environment->fhirUrl().'/'.$type, ['identifier' => self::NIK_SYSTEM.'|'.$nik]));
        $id = $response->json('entry.0.resource.id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     */
    private function send(SatuSehatSettings $settings, callable $call): Response
    {
        $response = $this->attempt($settings, $call, fresh: false);

        // Token ditolak: ambil token baru satu kali lalu ulangi.
        if ($response->status() === 401) {
            $this->forget($settings);
            $response = $this->attempt($settings, $call, fresh: true);
            if ($response->status() === 401) {
                throw SatuSehatFailure::permanent('Token SATUSEHAT ditolak. Periksa client ID dan client secret.');
            }
        }

        return $this->checked($response);
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     */
    private function attempt(SatuSehatSettings $settings, callable $call, bool $fresh): Response
    {
        $token = $this->token($settings, $fresh);
        try {
            return $call(Http::withToken($token)->acceptJson()->asJson()->timeout(20));
        } catch (ConnectionException) {
            throw SatuSehatFailure::transient('Tidak dapat terhubung ke SATUSEHAT.');
        }
    }

    private function checked(Response $response): Response
    {
        if ($response->successful()) {
            return $response;
        }

        $status = $response->status();
        if ($status === 429 || $status >= 500) {
            throw SatuSehatFailure::transient("SATUSEHAT sedang tidak tersedia (HTTP {$status}).");
        }

        throw SatuSehatFailure::permanent("SATUSEHAT menolak permintaan (HTTP {$status}){$this->outcome($response)}.");
    }

    /** Kode dan lokasi isian dari OperationOutcome; teks bebas tidak dipakai karena dapat memuat data pasien. */
    private function outcome(Response $response): string
    {
        $parts = [];
        foreach (array_slice((array) $response->json('issue', []), 0, 3) as $issue) {
            $code = is_string($issue['code'] ?? null) ? $issue['code'] : null;
            $where = is_array($issue['expression'] ?? null) && is_string($issue['expression'][0] ?? null) ? $issue['expression'][0] : null;
            if ($code !== null) {
                $parts[] = $code.($where !== null ? ' pada '.preg_replace('/[^A-Za-z0-9_.\[\]]/', '', $where) : '');
            }
        }

        return $parts === [] ? '' : ': '.implode('; ', $parts);
    }

    private function token(SatuSehatSettings $settings, bool $fresh = false): string
    {
        $key = $this->cacheKey($settings);
        if (! $fresh && is_string($cached = Cache::get($key))) {
            return $cached;
        }

        try {
            $response = Http::asForm()->acceptJson()->timeout(20)->post($settings->environment->authUrl().'/accesstoken?grant_type=client_credentials', [
                'client_id' => $settings->clientId,
                'client_secret' => $settings->clientSecret,
            ]);
        } catch (ConnectionException) {
            throw SatuSehatFailure::transient('Tidak dapat terhubung ke SATUSEHAT.');
        }

        if (! $response->successful()) {
            $status = $response->status();
            if ($status === 429 || $status >= 500) {
                throw SatuSehatFailure::transient("SATUSEHAT sedang tidak tersedia (HTTP {$status}).");
            }

            throw SatuSehatFailure::permanent('Client ID atau client secret ditolak SATUSEHAT.');
        }

        $token = $response->json('access_token');
        if (! is_string($token) || $token === '') {
            throw SatuSehatFailure::permanent('Respons token SATUSEHAT tidak sah.');
        }
        $ttl = max(60, (int) $response->json('expires_in', 3600) - 120);
        Cache::put($key, $token, $ttl);

        return $token;
    }

    private function forget(SatuSehatSettings $settings): void
    {
        Cache::forget($this->cacheKey($settings));
    }

    private function cacheKey(SatuSehatSettings $settings): string
    {
        // Rahasia tidak masuk kunci; cukup lingkungan dan client ID (di-hash).
        return 'satusehat.token.'.$settings->environment->value.'.'.hash('sha256', (string) $settings->clientId);
    }
}

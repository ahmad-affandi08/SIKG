<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure;

use Illuminate\Contracts\Encryption\StringEncrypter;
use RuntimeException;
use Src\Shared\Application\PiiProtector;

/**
 * Enkripsi memakai kunci aplikasi (AES-256), blind index memakai HMAC-SHA256 dengan kunci terpisah `PII_HASH_KEY`.
 * Tanpa kunci hash, pencarian dan penyimpanan PHI berhenti (gagal tertutup) alih-alih memakai hash tanpa kunci.
 */
final readonly class LaravelPiiProtector implements PiiProtector
{
    public function __construct(private StringEncrypter $encrypter, private ?string $hashKey) {}

    public function encrypt(string $plain): string
    {
        return $this->encrypter->encryptString($plain);
    }

    public function decrypt(string $cipher): string
    {
        return $this->encrypter->decryptString($cipher);
    }

    public function blindIndex(string $purpose, string $normalized): string
    {
        if ($this->hashKey === null || $this->hashKey === '') {
            throw new RuntimeException('PII_HASH_KEY belum diatur; data pribadi tidak dapat diproses.');
        }

        return hash_hmac('sha256', $purpose.':'.$normalized, $this->hashKey);
    }
}

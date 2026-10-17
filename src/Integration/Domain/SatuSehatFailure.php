<?php

declare(strict_types=1);

namespace Src\Integration\Domain;

use RuntimeException;

/**
 * Galat saat berbicara dengan SATUSEHAT. Pesannya sudah dibersihkan dari data pasien dan kredensial sehingga aman disimpan.
 * `retryable` menandai galat sementara (jaringan, 5xx, 429, token) yang pantas dicoba ulang.
 */
final class SatuSehatFailure extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable)
    {
        parent::__construct($message);
    }

    public static function transient(string $message): self
    {
        return new self($message, true);
    }

    public static function permanent(string $message): self
    {
        return new self($message, false);
    }
}

<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

use InvalidArgumentException;

/**
 * Nilai ULID tervalidasi sebagai dasar tipe ID tiap konteks (contoh `PatientId`).
 * Alfabet Crockford base32 tanpa I, L, O, U; karakter pertama paling tinggi 7 agar 128 bit tidak meluap.
 */
readonly class Ulid
{
    private const PATTERN = '/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/';

    final public function __construct(public string $value)
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException('ULID tidak valid');
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

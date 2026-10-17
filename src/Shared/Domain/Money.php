<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

use InvalidArgumentException;
use OverflowException;

/**
 * Uang adalah bilangan bulat rupiah (M4). Satu-satunya tempat aritmetika dan pembulatan uang.
 */
final readonly class Money
{
    private function __construct(public int $amount) {}

    public static function rupiah(int $amount): self
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Nilai uang tidak boleh negatif');
        }

        return new self($amount);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function add(self $other): self
    {
        return new self(self::exact($this->amount + $other->amount));
    }

    public function subtract(self $other): self
    {
        return self::rupiah($this->amount - $other->amount);
    }

    public function multiply(int $quantity): self
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException('Jumlah pengali tidak boleh negatif');
        }

        return new self(self::exact($this->amount * $quantity));
    }

    /**
     * Persentase dalam basis poin (1% = 100). Pembulatan setengah ke atas, satu-satunya aturan pembulatan.
     */
    public function percent(int $basisPoints): self
    {
        if ($basisPoints < 0) {
            throw new InvalidArgumentException('Persentase tidak boleh negatif');
        }

        return new self(intdiv(self::exact($this->amount * $basisPoints + 5000), 10000));
    }

    /**
     * Rata-rata per satuan (jumlah dibagi banyaknya), setengah ke atas. Satu-satunya pembulatan pembagian uang.
     */
    public function averageOver(int $count): self
    {
        if ($count < 1) {
            throw new InvalidArgumentException('Pembagi harus positif');
        }

        return new self(intdiv(self::exact($this->amount * 2 + $count), $count * 2));
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount;
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function greaterThan(self $other): bool
    {
        return $this->amount > $other->amount;
    }

    /**
     * PHP mengubah hasil yang meluap menjadi float secara diam-diam; uang tidak boleh begitu.
     */
    private static function exact(int|float $value): int
    {
        if (! is_int($value)) {
            throw new OverflowException('Nilai uang melebihi batas bilangan bulat');
        }

        return $value;
    }
}

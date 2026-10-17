<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure;

use Src\Shared\Domain\IdGenerator;

/**
 * ULID berurutan yang dapat diprediksi untuk tes: 01ARZ3NDEKTSV4RRFFQ69G5FA0, ...A1, dst.
 */
final class SequentialIdGenerator implements IdGenerator
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private int $counter = 0;

    public function generate(): string
    {
        $suffix = '';
        $n = $this->counter++;
        for ($i = 0; $i < 6; $i++) {
            $suffix = self::ALPHABET[$n % 32].$suffix;
            $n = intdiv($n, 32);
        }

        return '01ARZ3NDEKTSV4RRFF'.'Q6'.substr($suffix, 0, 6);
    }
}

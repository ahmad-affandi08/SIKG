<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

use DateTimeImmutable;

/**
 * Satu kondisi yang dicatat pada satu gigi dan satu permukaan (atau seluruh gigi) pada satu kunjungan.
 */
final readonly class ToothStateEntry
{
    public function __construct(
        public string $id,
        public int $toothFdi,
        public Surface $surface,
        public string $conditionCode,
        public DateTimeImmutable $setAt,
        public string $encounterId,
    ) {}
}

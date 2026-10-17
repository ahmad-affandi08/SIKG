<?php

declare(strict_types=1);

namespace Src\Clinical\Contracts;

use DateTimeImmutable;

interface RecallLookup
{
    /**
     * Kunjungan yang ditutup pada rentang waktu UTC dan merupakan kunjungan terakhir pasiennya yang sudah ditutup
     * (tidak ada kunjungan tertutup yang lebih baru).
     *
     * @return list<RecallCandidate> terlama dulu
     */
    public function latestClosedBetween(DateTimeImmutable $from, DateTimeImmutable $to): array;
}

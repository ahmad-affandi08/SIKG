<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Src\Shared\Application\Clock;

/**
 * Jam palsu untuk tes dan seeder: waktu tetap sampai digeser.
 */
final class FrozenClock implements Clock
{
    private DateTimeImmutable $current;

    public function __construct(string $instant)
    {
        $this->current = new DateTimeImmutable($instant, new DateTimeZone('UTC'));
    }

    public function now(): DateTimeImmutable
    {
        return $this->current;
    }

    public function advance(string $interval): void
    {
        $this->current = $this->current->add(new DateInterval($interval));
    }

    public function travelTo(string $instant): void
    {
        $this->current = new DateTimeImmutable($instant, new DateTimeZone('UTC'));
    }
}

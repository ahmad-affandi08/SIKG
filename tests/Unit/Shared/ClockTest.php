<?php

declare(strict_types=1);

use Src\Shared\Infrastructure\FrozenClock;
use Src\Shared\Infrastructure\SystemClock;

it('NFR-12 jam sistem selalu UTC', function () {
    $now = (new SystemClock)->now();

    expect($now->getTimezone()->getName())->toBe('UTC');
});

it('NFR-12 jam palsu membeku sampai digeser', function () {
    $clock = new FrozenClock('2030-05-17 10:00:00');

    expect($clock->now()->format('Y-m-d H:i:s'))->toBe('2030-05-17 10:00:00')
        ->and($clock->now()->format('Y-m-d H:i:s'))->toBe('2030-05-17 10:00:00');

    $clock->advance('PT90M');
    expect($clock->now()->format('Y-m-d H:i:s'))->toBe('2030-05-17 11:30:00');

    $clock->travelTo('2031-01-01 00:00:00');
    expect($clock->now()->format('Y-m-d'))->toBe('2031-01-01');
});

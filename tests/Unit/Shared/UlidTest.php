<?php

declare(strict_types=1);

use Src\Shared\Domain\Ulid;
use Src\Shared\Infrastructure\SequentialIdGenerator;
use Src\Shared\Infrastructure\UlidIdGenerator;

it('ADR-0004 ULID yang dibuat generator sah dan unik', function () {
    $generator = new UlidIdGenerator;
    $ids = array_map(fn () => $generator->generate(), range(1, 200));

    foreach ($ids as $id) {
        expect(strlen($id))->toBe(26)
            ->and(fn () => new Ulid($id))->not->toThrow(InvalidArgumentException::class);
    }
    expect(array_unique($ids))->toHaveCount(200);
});

it('ADR-0004 ULID menolak nilai tidak sah', function (string $value) {
    new Ulid($value);
})->with([
    'terlalu pendek' => ['01ARZ3NDEK'],
    'huruf terlarang' => ['01ARZ3NDEKTSV4RRFFQ69G5FAI'],
    'kapital kecil' => ['01arz3ndektsv4rrffq69g5fav'],
    'meluap 128 bit' => ['81ARZ3NDEKTSV4RRFFQ69G5FAV'],
    'kosong' => [''],
])->throws(InvalidArgumentException::class);

it('ADR-0004 ULID dibandingkan dengan nilai', function () {
    $a = new Ulid('01ARZ3NDEKTSV4RRFFQ69G5FAV');

    expect($a->equals(new Ulid('01ARZ3NDEKTSV4RRFFQ69G5FAV')))->toBeTrue()
        ->and((string) $a)->toBe('01ARZ3NDEKTSV4RRFFQ69G5FAV');
});

it('generator berurutan untuk tes menghasilkan ULID sah yang dapat diprediksi', function () {
    $generator = new SequentialIdGenerator;
    $first = $generator->generate();
    $second = $generator->generate();

    expect(fn () => new Ulid($first))->not->toThrow(InvalidArgumentException::class)
        ->and(fn () => new Ulid($second))->not->toThrow(InvalidArgumentException::class)
        ->and($second)->not->toBe($first)
        ->and((new SequentialIdGenerator)->generate())->toBe($first);
});

<?php

declare(strict_types=1);

use Src\Shared\Domain\DomainException;

final class ContohKonflik extends DomainException
{
    public function __construct()
    {
        parent::__construct('appointment.conflict', 'Jadwal bentrok dengan janji lain.');
    }

    public function httpStatus(): int
    {
        return 409;
    }
}

it('JDW-02 galat domain membawa kode mesin yang stabil dan pesan Indonesia', function () {
    $e = new ContohKonflik;

    expect($e->businessCode())->toBe('appointment.conflict')
        ->and($e->getMessage())->toBe('Jadwal bentrok dengan janji lain.')
        ->and($e->httpStatus())->toBe(409);
});

it('galat domain bawaan memakai status 422', function () {
    $e = new class('stock.insufficient', 'Stok tidak cukup.') extends DomainException {};

    expect($e->httpStatus())->toBe(422);
});

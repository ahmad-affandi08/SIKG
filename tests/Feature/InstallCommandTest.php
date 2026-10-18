<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Src\IdentityAccess\Infrastructure\Eloquent\User;

const KATA_SANDI_SEMENTARA = 'sementara-12345';

it('AUTH-01 install: membuat klinik dan Pemilik dengan kata sandi sementara', function () {
    $this->artisan('sikg:install', [
        '--clinic' => 'Klinik Contoh Sehat', '--name' => 'Pemilik Contoh', '--username' => 'pemilik',
        '--email' => 'pemilik@contoh.test', '--password' => KATA_SANDI_SEMENTARA,
    ])->assertExitCode(0);

    expect(DB::table('clinics')->count())->toBe(1);
    tenant()->set((string) DB::table('clinics')->value('id'));
    $pemilik = User::query()->where('username', 'pemilik')->firstOrFail();
    expect($pemilik->must_change_password)->toBeTrue()
        ->and($pemilik->hasRole('pemilik'))->toBeTrue()
        ->and(Hash::check(KATA_SANDI_SEMENTARA, $pemilik->password))->toBeTrue();
});

it('AUTH-01 install: menolak bila sudah ada klinik', function () {
    makeClinic('Klinik Lama');

    $this->artisan('sikg:install', [
        '--clinic' => 'Klinik Baru', '--name' => 'X Y', '--username' => 'xy', '--email' => 'xy@contoh.test', '--password' => KATA_SANDI_SEMENTARA,
    ])->assertExitCode(1);

    expect(DB::table('clinics')->count())->toBe(1);
});

it('AUTH-01 install: menolak kata sandi pendek dan email tidak valid', function () {
    $this->artisan('sikg:install', [
        '--clinic' => 'Klinik', '--name' => 'A B', '--username' => 'ab', '--email' => 'ab@contoh.test', '--password' => 'pendek',
    ])->assertExitCode(1);
    $this->artisan('sikg:install', [
        '--clinic' => 'Klinik', '--name' => 'A B', '--username' => 'ab', '--email' => 'bukan-email', '--password' => KATA_SANDI_SEMENTARA,
    ])->assertExitCode(1);

    expect(DB::table('clinics')->count())->toBe(0);
});

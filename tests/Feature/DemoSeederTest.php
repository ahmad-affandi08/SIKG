<?php

declare(strict_types=1);

use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

afterEach(function () {
    putenv('DEMO_PASSWORD');
    putenv('DEMO_ALL_DAY');
});

it('SYS-06 DemoSeeder menautkan dokter ke akunnya, menyiapkan metode bayar, dan memakai kata sandi sintetis yang diberikan', function () {
    putenv('DEMO_PASSWORD=sandi-uji-lokal');

    $this->seed(DemoSeeder::class);

    $sari = DB::table('users')->where('username', 'sari')->first();
    $dokter = DB::table('doctors')->first();
    expect($dokter->user_id)->toBe($sari->id)
        ->and(DB::table('payment_methods')->count())->toBe(5)
        ->and(Hash::check('sandi-uji-lokal', (string) $sari->password))->toBeTrue()
        ->and(DB::table('doctor_schedules')->count())->toBe(7)
        ->and(DB::table('doctor_schedules')->value('start_time'))->toBe('09:00:00');
});

it('SYS-06 DEMO_ALL_DAY membuat jadwal dokter sehari penuh agar tes E2E tidak bergantung pada jam berjalan', function () {
    putenv('DEMO_ALL_DAY=1');

    $this->seed(DemoSeeder::class);

    expect(DB::table('doctor_schedules')->value('start_time'))->toBe('00:00:00')
        ->and(DB::table('doctor_schedules')->value('end_time'))->toBe('23:59:00');
});

it('SYS-06 DemoSeeder tidak menambah data bila sudah ada klinik', function () {
    $this->seed(DemoSeeder::class);
    $this->seed(DemoSeeder::class);

    expect(DB::table('clinics')->count())->toBe(1);
});

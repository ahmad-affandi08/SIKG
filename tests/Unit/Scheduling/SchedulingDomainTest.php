<?php

declare(strict_types=1);

use Src\Scheduling\Domain\AppointmentStatus;
use Src\Scheduling\Domain\InvalidAppointment;
use Src\Scheduling\Domain\PracticeHours;
use Src\Scheduling\Domain\TimeSlot;

function waktu(string $t): DateTimeImmutable
{
    return new DateTimeImmutable($t, new DateTimeZone('Asia/Jakarta'));
}

it('JDW-04 alur status janji hanya boleh maju sesuai aturan', function () {
    $S = AppointmentStatus::class;

    expect(AppointmentStatus::Scheduled->canBecome(AppointmentStatus::Confirmed))->toBeTrue()
        ->and(AppointmentStatus::Scheduled->canBecome(AppointmentStatus::Completed))->toBeFalse()
        ->and(AppointmentStatus::CheckedIn->canBecome(AppointmentStatus::InProgress))->toBeTrue()
        ->and(AppointmentStatus::CheckedIn->canBecome(AppointmentStatus::NoShow))->toBeFalse()
        ->and(AppointmentStatus::InProgress->canBecome(AppointmentStatus::Cancelled))->toBeFalse()
        ->and(AppointmentStatus::InProgress->canBecome(AppointmentStatus::Completed))->toBeTrue();
    foreach ([AppointmentStatus::Completed, AppointmentStatus::Cancelled, AppointmentStatus::NoShow] as $akhir) {
        expect($akhir->next())->toBe([]);
    }
    expect(count($S::cases()))->toBe(7);
});

it('JDW-04 label status sesuai istilah PRD', function () {
    expect(array_map(fn (AppointmentStatus $s) => $s->label(), AppointmentStatus::cases()))
        ->toBe(['Dijadwalkan', 'Dikonfirmasi', 'Check-in', 'Sedang diperiksa', 'Selesai', 'Batal', 'Tidak datang']);
});

it('JDW-02 janji batal dan tidak datang tidak lagi menempati slot', function () {
    expect(AppointmentStatus::Cancelled->occupiesSlot())->toBeFalse()
        ->and(AppointmentStatus::NoShow->occupiesSlot())->toBeFalse()
        ->and(AppointmentStatus::Scheduled->occupiesSlot())->toBeTrue()
        ->and(AppointmentStatus::occupyingValues())->not->toContain('cancelled', 'no_show');
});

it('JDW-02 rentang waktu setengah terbuka: selesai tepat saat berikutnya mulai tidak bentrok', function () {
    $a = TimeSlot::of(waktu('2025-06-16 09:00'), 30);
    $berikut = TimeSlot::of(waktu('2025-06-16 09:30'), 30);
    $tumpang = TimeSlot::of(waktu('2025-06-16 09:15'), 30);

    expect($a->overlaps($berikut))->toBeFalse()
        ->and($berikut->overlaps($a))->toBeFalse()
        ->and($a->overlaps($tumpang))->toBeTrue()
        ->and($tumpang->overlaps($berikut))->toBeTrue()
        ->and($a->minutes())->toBe(30);
});

it('JDW-01 durasi janji dibatasi 10 sampai 480 menit', function () {
    expect(fn () => TimeSlot::of(waktu('2025-06-16 09:00'), 5))->toThrow(InvalidAppointment::class)
        ->and(fn () => TimeSlot::of(waktu('2025-06-16 09:00'), 481))->toThrow(InvalidAppointment::class);
    expect(TimeSlot::of(waktu('2025-06-16 09:00'), 10)->minutes())->toBe(10);
});

it('JDW-02 jam praktik: seluruh rentang harus berada dalam satu blok pada hari yang sama', function () {
    $jam = new PracticeHours([
        ['weekday' => 1, 'start' => '09:00', 'end' => '12:00'],
        ['weekday' => 1, 'start' => '14:00', 'end' => '17:00'],
    ]);

    expect($jam->covers(waktu('2025-06-16 09:00'), waktu('2025-06-16 12:00')))->toBeTrue()
        ->and($jam->covers(waktu('2025-06-16 11:30'), waktu('2025-06-16 12:30')))->toBeFalse()
        ->and($jam->covers(waktu('2025-06-16 12:00'), waktu('2025-06-16 14:30')))->toBeFalse()
        ->and($jam->covers(waktu('2025-06-17 09:00'), waktu('2025-06-17 10:00')))->toBeFalse()
        ->and($jam->onWeekday(1))->toHaveCount(2)
        ->and((new PracticeHours([]))->isEmpty())->toBeTrue();
});

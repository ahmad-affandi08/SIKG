<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure;

use DateTimeZone;
use InvalidArgumentException;
use Src\Clinic\Application\Settings\ClinicSettingsService;
use Src\Clinic\Domain\ClinicSettings;
use Src\Clinic\Domain\HolidayAlreadyExists;
use Src\Clinic\Infrastructure\Eloquent\ClinicModel;
use Src\Clinic\Infrastructure\Eloquent\HolidayModel;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Transaction;
use Src\Shared\Domain\ServiceProfile;

final readonly class EloquentClinicSettingsService implements ClinicSettingsService
{
    public function __construct(private Transaction $tx, private AuditLogger $audit, private Clock $clock) {}

    public function overview(): array
    {
        $clinic = ClinicModel::query()->firstOrFail();
        $settings = $clinic->clinicSettings();
        $now = $this->clock->now();

        return [
            'timezone' => $clinic->timezone,
            'settings' => $settings->toArray(),
            'example' => [
                'medicalRecordNo' => $settings->medicalRecordPattern->render(1, $now, $clinic->timezone),
                'invoiceNo' => $settings->invoicePattern->render(1, $now, $clinic->timezone),
            ],
            'holidays' => array_values(HolidayModel::query()->orderByDesc('date')->limit(100)->get()
                ->map(fn (HolidayModel $h) => ['id' => $h->id, 'date' => $h->date->toDateString(), 'name' => $h->name])
                ->all()),
        ];
    }

    public function update(string $actorId, string $timezone, ClinicSettings $settings): void
    {
        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException('Zona waktu tidak dikenal.');
        }

        $this->tx->run(function () use ($actorId, $timezone, $settings): void {
            $clinic = ClinicModel::query()->lockForUpdate()->firstOrFail();
            $before = ['timezone' => $clinic->timezone, 'settings' => $clinic->clinicSettings()->toArray()];
            // Profil layanan hanya berubah lewat changeServiceProfile(); formulir pengaturan umum tidak boleh menimpanya.
            $settings = $settings->withServiceProfile($clinic->clinicSettings()->serviceProfile);

            $clinic->forceFill(['timezone' => $timezone, 'settings' => $settings->toArray(), 'updated_by' => $actorId])->save();

            $this->audit->record(new AuditEntry(
                actorId: $actorId,
                action: 'clinic.settings_updated',
                subjectType: 'clinic',
                subjectId: $clinic->id,
                before: $before,
                after: ['timezone' => $timezone, 'settings' => $settings->toArray()],
            ));
        });
    }

    public function changeServiceProfile(string $actorId, ServiceProfile $profile): void
    {
        $this->tx->run(function () use ($actorId, $profile): void {
            $clinic = ClinicModel::query()->lockForUpdate()->firstOrFail();
            $current = $clinic->clinicSettings();
            if ($current->serviceProfile === $profile) {
                return;
            }

            $clinic->forceFill(['settings' => $current->withServiceProfile($profile)->toArray(), 'updated_by' => $actorId])->save();

            $this->audit->record(new AuditEntry(
                actorId: $actorId,
                action: 'clinic.service_profile_changed',
                subjectType: 'clinic',
                subjectId: $clinic->id,
                before: ['service_profile' => $current->serviceProfile->value],
                after: ['service_profile' => $profile->value],
            ));
        });
    }

    public function addHoliday(string $actorId, string $date, string $name): void
    {
        $this->tx->run(function () use ($actorId, $date, $name): void {
            if (HolidayModel::query()->whereDate('date', $date)->exists()) {
                throw new HolidayAlreadyExists;
            }

            $holiday = new HolidayModel;
            $holiday->forceFill(['date' => $date, 'name' => $name, 'created_by' => $actorId])->save();

            $this->audit->record(new AuditEntry($actorId, 'holiday.created', 'holiday', $holiday->id, null, ['date' => $date, 'name' => $name]));
        });
    }

    public function removeHoliday(string $actorId, string $holidayId): void
    {
        $this->tx->run(function () use ($actorId, $holidayId): void {
            $holiday = HolidayModel::query()->findOrFail($holidayId);
            $before = ['date' => $holiday->date->toDateString(), 'name' => $holiday->name];
            $holiday->delete();

            $this->audit->record(new AuditEntry($actorId, 'holiday.deleted', 'holiday', $holidayId, $before, null));
        });
    }
}

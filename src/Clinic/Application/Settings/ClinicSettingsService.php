<?php

declare(strict_types=1);

namespace Src\Clinic\Application\Settings;

use Src\Clinic\Domain\ClinicSettings;

/**
 * SYS-04 dan AUTH-04: pengaturan klinik dan hari libur. Port Application untuk konteks CRUD terstruktur (ADR-0001);
 * implementasi Eloquent ada di Infrastructure. Perubahan tercatat di audit.
 */
interface ClinicSettingsService
{
    /**
     * @return array{timezone: string, settings: array<string, mixed>, example: array{medicalRecordNo: string, invoiceNo: string}, holidays: list<array{id: string, date: string, name: string}>}
     */
    public function overview(): array;

    public function update(string $actorId, string $timezone, ClinicSettings $settings): void;

    public function addHoliday(string $actorId, string $date, string $name): void;

    public function removeHoliday(string $actorId, string $holidayId): void;
}

<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure;

use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Src\Clinic\Application\Profile\ClinicProfile;
use Src\Clinic\Application\Profile\ClinicProfileService;
use Src\Clinic\Infrastructure\Eloquent\ClinicModel;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Transaction;
use Src\Shared\Domain\IdGenerator;

final readonly class EloquentClinicProfileService implements ClinicProfileService
{
    private const MIME = ['png' => 'image/png', 'jpg' => 'image/jpeg'];

    public function __construct(private Transaction $tx, private AuditLogger $audit, private IdGenerator $ids) {}

    public function get(): ClinicProfile
    {
        $clinic = ClinicModel::query()->firstOrFail();

        return new ClinicProfile($clinic->name, $clinic->address, $clinic->city, $clinic->license_no, $clinic->receipt_header, $clinic->prescription_header, $clinic->logo_path !== null);
    }

    public function update(string $actorId, ClinicProfile $profile): void
    {
        $this->tx->run(function () use ($actorId, $profile): void {
            $clinic = ClinicModel::query()->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($clinic);

            $clinic->forceFill([
                'name' => $profile->name,
                'address' => $profile->address,
                'city' => $profile->city,
                'license_no' => $profile->licenseNo,
                'receipt_header' => $profile->receiptHeader,
                'prescription_header' => $profile->prescriptionHeader,
                'updated_by' => $actorId,
            ])->save();

            $this->audit->record(new AuditEntry($actorId, 'clinic.profile_updated', 'clinic', $clinic->id, $before, $this->snapshot($clinic)));
        });
    }

    public function replaceLogo(string $actorId, string $contents, string $extension): void
    {
        if (! isset(self::MIME[$extension])) {
            throw new InvalidArgumentException('Format logo tidak didukung.');
        }

        $this->tx->run(function () use ($actorId, $contents, $extension): void {
            $clinic = ClinicModel::query()->lockForUpdate()->firstOrFail();
            $old = $clinic->logo_path;
            $path = "clinics/{$clinic->id}/logo-{$this->ids->generate()}.{$extension}";

            Storage::disk('private')->put($path, $contents);
            $clinic->forceFill(['logo_path' => $path, 'updated_by' => $actorId])->save();
            if ($old !== null) {
                Storage::disk('private')->delete($old);
            }

            $this->audit->record(new AuditEntry($actorId, 'clinic.logo_replaced', 'clinic', $clinic->id, ['has_logo' => $old !== null], ['has_logo' => true]));
        });
    }

    public function removeLogo(string $actorId): void
    {
        $this->tx->run(function () use ($actorId): void {
            $clinic = ClinicModel::query()->lockForUpdate()->firstOrFail();
            $old = $clinic->logo_path;
            if ($old === null) {
                return;
            }

            $clinic->forceFill(['logo_path' => null, 'updated_by' => $actorId])->save();
            Storage::disk('private')->delete($old);

            $this->audit->record(new AuditEntry($actorId, 'clinic.logo_removed', 'clinic', $clinic->id, ['has_logo' => true], ['has_logo' => false]));
        });
    }

    public function logo(): ?array
    {
        $path = ClinicModel::query()->firstOrFail()->logo_path;
        if ($path === null || ! Storage::disk('private')->exists($path)) {
            return null;
        }

        return ['path' => $path, 'mime' => self::MIME[pathinfo($path, PATHINFO_EXTENSION)] ?? 'application/octet-stream'];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(ClinicModel $clinic): array
    {
        return [
            'name' => $clinic->name,
            'address' => $clinic->address,
            'city' => $clinic->city,
            'license_no' => $clinic->license_no,
            'receipt_header' => $clinic->receipt_header,
            'prescription_header' => $clinic->prescription_header,
        ];
    }
}

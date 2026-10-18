<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure\Loyalty;

use Src\Billing\Domain\Loyalty\PackageRepository;
use Src\Billing\Domain\Loyalty\PatientPackage;
use Src\Billing\Infrastructure\Eloquent\PackageUsageModel;
use Src\Billing\Infrastructure\Eloquent\PatientPackageModel;
use Src\Billing\Infrastructure\Eloquent\VisitPackageModel;

final class EloquentPackageRepository implements PackageRepository
{
    public function catalog(string $packageId): ?array
    {
        $m = VisitPackageModel::query()->find($packageId);

        return $m === null ? null : [
            'id' => $m->id, 'name' => $m->name, 'sessions' => $m->sessions, 'price_amount' => $m->price_amount,
            'max_session_value_amount' => $m->max_session_value_amount, 'valid_days' => $m->valid_days, 'active' => $m->is_active,
        ];
    }

    public function activeCatalog(): array
    {
        return array_values(VisitPackageModel::query()->where('is_active', true)->orderBy('name')->get()
            ->map(fn (VisitPackageModel $m) => ['id' => $m->id, 'name' => $m->name, 'sessions' => $m->sessions, 'price_amount' => $m->price_amount])->all());
    }

    public function add(PatientPackage $package, int $priceAmount, string $packageId, string $purchasedOn, ?string $invoiceId, string $actorId): void
    {
        $m = new PatientPackageModel;
        $m->id = $package->id;
        $m->forceFill([
            'patient_id' => $package->patientId, 'package_id' => $packageId, 'name' => $package->name, 'sessions_total' => $package->sessionsTotal, 'sessions_left' => $package->sessionsLeft,
            'price_amount' => $priceAmount, 'max_session_value_amount' => $package->maxSessionValueAmount, 'purchased_on' => $purchasedOn, 'expires_on' => $package->expiresOn,
            'invoice_id' => $invoiceId, 'status' => 'active', 'created_by' => $actorId,
        ])->save();
    }

    public function lock(string $patientPackageId): ?PatientPackage
    {
        $m = PatientPackageModel::query()->lockForUpdate()->find($patientPackageId);

        return $m === null ? null : $this->record($m);
    }

    public function forPatient(string $patientId): array
    {
        return array_values(PatientPackageModel::query()->where('patient_id', $patientId)->orderByDesc('purchased_on')->orderByDesc('id')->get()
            ->map(fn (PatientPackageModel $m) => $this->record($m))->all());
    }

    public function changeSessions(string $patientPackageId, int $delta): void
    {
        $m = PatientPackageModel::query()->lockForUpdate()->findOrFail($patientPackageId);
        $m->forceFill(['sessions_left' => max(0, min($m->sessions_total, $m->sessions_left + $delta))])->save();
    }

    public function addUsage(string $patientPackageId, string $invoiceId, string $itemId, int $valueAmount, string $actorId): void
    {
        (new PackageUsageModel)->forceFill([
            'patient_package_id' => $patientPackageId, 'invoice_id' => $invoiceId, 'item_id' => $itemId, 'value_amount' => $valueAmount, 'status' => 'applied', 'created_by' => $actorId,
        ])->save();
    }

    public function appliedUsages(string $invoiceId): array
    {
        return array_values(PackageUsageModel::query()->where('invoice_id', $invoiceId)->where('status', 'applied')->get()
            ->map(fn (PackageUsageModel $u) => ['id' => $u->id, 'patient_package_id' => $u->patient_package_id, 'item_id' => $u->item_id, 'value_amount' => $u->value_amount])->all());
    }

    public function releaseUsage(string $usageId): void
    {
        PackageUsageModel::query()->whereKey($usageId)->update(['status' => 'released']);
    }

    public function voidPurchasedBy(string $invoiceId): int
    {
        return PatientPackageModel::query()->where('invoice_id', $invoiceId)->where('status', 'active')->update(['status' => 'void']);
    }

    public function itemHasUsage(string $itemId): bool
    {
        return PackageUsageModel::query()->where('item_id', $itemId)->where('status', 'applied')->exists();
    }

    private function record(PatientPackageModel $m): PatientPackage
    {
        return new PatientPackage($m->id, $m->patient_id, $m->name, $m->sessions_total, $m->sessions_left, $m->max_session_value_amount, $m->expires_on?->toDateString(), $m->status);
    }
}

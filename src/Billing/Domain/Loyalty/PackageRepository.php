<?php

declare(strict_types=1);

namespace Src\Billing\Domain\Loyalty;

interface PackageRepository
{
    /**
     * @return array{id: string, name: string, sessions: int, price_amount: int, max_session_value_amount: int|null, valid_days: int|null, active: bool}|null
     */
    public function catalog(string $packageId): ?array;

    /**
     * @return list<array{id: string, name: string, sessions: int, price_amount: int}> paket katalog aktif
     */
    public function activeCatalog(): array;

    public function add(PatientPackage $package, int $priceAmount, string $packageId, string $purchasedOn, ?string $invoiceId, string $actorId): void;

    public function lock(string $patientPackageId): ?PatientPackage;

    /**
     * @return list<PatientPackage> paket pasien, terbaru lebih dulu
     */
    public function forPatient(string $patientId): array;

    public function changeSessions(string $patientPackageId, int $delta): void;

    public function addUsage(string $patientPackageId, string $invoiceId, string $itemId, int $valueAmount, string $actorId): void;

    /**
     * @return list<array{id: string, patient_package_id: string, item_id: string, value_amount: int}>
     */
    public function appliedUsages(string $invoiceId): array;

    public function releaseUsage(string $usageId): void;

    /**
     * Membatalkan paket yang dibeli lewat tagihan ini (tagihan dibatalkan); mengembalikan jumlah paket yang dibatalkan.
     */
    public function voidPurchasedBy(string $invoiceId): int;

    public function itemHasUsage(string $itemId): bool;
}

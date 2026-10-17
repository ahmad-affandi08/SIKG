<?php

declare(strict_types=1);

namespace Src\Orthodontics\Domain;

use DateTimeImmutable;

interface OrthoRepository
{
    /**
     * Menyimpan paket beserta jadwal cicilannya.
     *
     * @param  list<array{seq: int, due_date: string, amount: int}>  $schedule
     */
    public function addCase(OrthoCase $case, array $schedule, string $actorId): void;

    public function find(string $id): ?OrthoCase;

    /** Mengunci baris paket selama transaksi pemanggil. */
    public function lock(string $id): ?OrthoCase;

    public function save(OrthoCase $case, string $actorId): void;

    /**
     * @return list<OrthoCase> terbaru dulu
     */
    public function list(?string $status, ?string $patientId, int $limit): array;

    /**
     * @return list<InstallmentRecord> urut nomor cicilan
     */
    public function installments(string $caseId): array;

    public function findInstallment(string $id): ?InstallmentRecord;

    /** Mengunci baris cicilan selama transaksi pemanggil. */
    public function lockInstallment(string $id): ?InstallmentRecord;

    public function markInvoiced(string $installmentId, string $encounterId, string $invoiceId, DateTimeImmutable $at): void;

    /** Membatalkan cicilan yang belum ditagihkan; mengembalikan jumlahnya. */
    public function cancelOpenInstallments(string $caseId): int;

    public function addControl(string $actorId, string $caseId, string $controlDate, ?string $encounterId, ?string $wireOrTray, ?string $adjustments, ?string $notes, ?string $nextControlDate): string;

    /**
     * @return list<ControlRecord> terbaru dulu
     */
    public function controls(string $caseId): array;

    /**
     * Kontrol terakhir per paket.
     *
     * @param  list<string>  $caseIds
     * @return array<string, ControlRecord>
     */
    public function latestControls(array $caseIds): array;

    /**
     * Sisa cicilan yang belum ditagihkan per paket.
     *
     * @param  list<string>  $caseIds
     * @return array<string, int> case_id => jumlah rupiah
     */
    public function unbilledAmounts(array $caseIds): array;
}

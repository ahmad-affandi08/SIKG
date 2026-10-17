<?php

declare(strict_types=1);

namespace Src\Insurance\Domain;

interface ClaimRepository
{
    public function add(Claim $claim, string $actorId): void;

    public function find(string $id): ?Claim;

    /** Mengunci baris klaim selama transaksi pemanggil. */
    public function lock(string $id): ?Claim;

    public function save(Claim $claim, string $actorId): void;

    /**
     * @return list<Claim> terbaru dulu
     */
    public function list(?string $status, ?string $insurerId, ?string $patientId, int $limit): array;

    /**
     * @return list<Claim> klaim diajukan atau disetujui yang masih punya piutang
     */
    public function open(): array;

    /**
     * Jumlah dasar klaim yang masih dihitung pada satu tagihan.
     */
    public function committedForInvoice(string $invoiceId, ?string $exceptClaimId = null): int;

    public function addReceipt(ClaimReceipt $receipt): void;

    public function findReceipt(string $claimId, string $idempotencyKey): ?ClaimReceipt;

    /**
     * @return list<ClaimReceipt> terlama dulu
     */
    public function receipts(string $claimId): array;
}

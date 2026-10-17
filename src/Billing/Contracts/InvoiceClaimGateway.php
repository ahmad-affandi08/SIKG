<?php

declare(strict_types=1);

namespace Src\Billing\Contracts;

use Src\Shared\Domain\DomainException;

/**
 * Kontrak Billing untuk konteks Insurance (P2-02): membaca tagihan yang akan diklaim dan mencatat uang yang diterima dari
 * penjamin sebagai pembayaran pada tagihan itu. Pencatatan memakai kunci idempoten sehingga pengulangan tidak menggandakan uang (M7).
 */
interface InvoiceClaimGateway
{
    public function find(string $invoiceId): ?InvoiceClaimInfo;

    /**
     * @param  list<string>  $invoiceIds
     * @return array<string, InvoiceClaimInfo> invoice_id => ringkasan
     */
    public function byIds(array $invoiceIds): array;

    /**
     * Mencatat pembayaran dari penjamin pada tagihan.
     *
     * @throws DomainException bila pembayaran ditolak aturan tagihan (contoh melebihi sisa)
     */
    public function recordPayment(string $actorId, string $invoiceId, string $methodId, int $amount, string $idempotencyKey, ?string $referenceNo): void;
}

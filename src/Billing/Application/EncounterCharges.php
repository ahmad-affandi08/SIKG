<?php

declare(strict_types=1);

namespace Src\Billing\Application;

use Src\Billing\Domain\Invoice;
use Src\Billing\Domain\InvoiceItem;
use Src\Billing\Domain\InvoiceRepository;
use Src\Clinic\Contracts\TaxRate;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Transaction;
use Src\Shared\Domain\IdGenerator;
use Src\Shared\Domain\Money;

/**
 * Menambahkan biaya non-tindakan ke draft tagihan kunjungan (P2-04). Draft dibuat bila belum ada. Biaya dengan sumber yang sama
 * tidak masuk dua kali; tagihan yang sudah terbit menolak (item klinis tidak dapat ditambah).
 */
final readonly class EncounterCharges
{
    public function __construct(
        private InvoiceRepository $invoices,
        private Transaction $tx,
        private AuditLogger $audit,
        private IdGenerator $ids,
        private TaxRate $tax,
    ) {}

    public function add(string $encounterId, string $patientId, string $sourceType, string $sourceId, string $description, int $amount): string
    {
        return $this->tx->run(function () use ($encounterId, $patientId, $sourceType, $sourceId, $description, $amount): string {
            $invoice = $this->invoices->lockByEncounter($encounterId) ?? Invoice::draft($this->ids->generate(), $patientId, $encounterId, $this->tax->basisPoints());
            if ($invoice->hasSource($sourceType, $sourceId)) {
                return $invoice->id;
            }
            $invoice->addClinicalItem(InvoiceItem::create($this->ids->generate(), $sourceType, $sourceId, $description, 1, Money::rupiah($amount)));
            $this->invoices->save($invoice, null);
            $this->audit->record(new AuditEntry(null, 'invoice.item_added', 'invoice', $invoice->id, null, [
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'quantity' => 1,
                'unit_price' => $amount,
            ]));

            return $invoice->id;
        });
    }
}

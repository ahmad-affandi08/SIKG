<?php

declare(strict_types=1);

namespace Src\Billing\Application;

use Src\Billing\Domain\Events\InvoiceIssued;
use Src\Billing\Domain\InvalidBillingData;
use Src\Billing\Domain\Invoice;
use Src\Billing\Domain\InvoiceItem;
use Src\Billing\Domain\InvoiceRepository;
use Src\Clinical\Domain\Events\EncounterClosed;
use Src\Clinical\Domain\Events\ProcedurePerformed;
use Src\Clinical\Domain\Events\ProcedureRetracted;
use Src\Pharmacy\Domain\Events\MedicineDispensed;
use Src\Pharmacy\Domain\Events\MedicineReturned;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Transaction;
use Src\Shared\Domain\IdGenerator;
use Src\Shared\Domain\Money;

/**
 * KSR-01 dan MST-08: draft tagihan dibuat dan diisi otomatis dari tindakan dan obat pada kunjungan, lalu diterbitkan saat
 * kunjungan ditutup. Harga yang dibawa event sudah merupakan salinan tarif saat tindakan atau obat dicatat; di sini ia
 * disalin lagi ke item, sehingga perubahan tarif di katalog tidak pernah menyentuh tagihan yang sudah ada.
 * Event datang setelah commit transaksi Clinical atau Pharmacy; setiap penanganan berjalan di transaksinya sendiri dan
 * idempoten (item dengan sumber yang sama tidak dibuat dua kali).
 */
final readonly class InvoiceDrafting
{
    public function __construct(
        private InvoiceRepository $invoices,
        private BillingNumbering $numbering,
        private Transaction $tx,
        private AuditLogger $audit,
        private EventDispatcher $events,
        private IdGenerator $ids,
        private Clock $clock,
    ) {}

    public function procedurePerformed(ProcedurePerformed $event): void
    {
        $this->addItem(
            $event->encounterId,
            $event->patientId,
            InvoiceItem::SOURCE_PROCEDURE,
            $event->encounterProcedureId,
            trim($event->procedureCode.' '.$event->procedureName),
            $event->quantity,
            $event->unitPriceAmount,
        );
    }

    public function medicineDispensed(MedicineDispensed $event): void
    {
        $this->addItem(
            $event->encounterId,
            $event->patientId,
            InvoiceItem::SOURCE_MEDICINE,
            $event->prescriptionItemId,
            $event->medicineName,
            $event->quantity,
            $event->unitPriceAmount,
        );
    }

    public function procedureRetracted(ProcedureRetracted $event): void
    {
        $this->release($event->encounterId, InvoiceItem::SOURCE_PROCEDURE, $event->encounterProcedureId);
    }

    public function medicineReturned(MedicineReturned $event): void
    {
        $this->release($event->encounterId, InvoiceItem::SOURCE_MEDICINE, $event->prescriptionItemId);
    }

    /**
     * Menerbitkan draft saat kunjungan ditutup. Tanpa item tidak ada tagihan. Uang muka yang melebihi total menahan
     * penerbitan (kasir menyelesaikannya) alih-alih menggagalkan penutupan kunjungan yang sudah ter-commit.
     */
    public function encounterClosed(EncounterClosed $event): void
    {
        $issued = $this->tx->run(function () use ($event): ?InvoiceIssued {
            $invoice = $this->invoices->lockByEncounter($event->encounterId);
            if ($invoice === null || $invoice->lifecycle() !== Invoice::DRAFT || $invoice->items() === []) {
                return null;
            }
            $now = $this->clock->now();
            try {
                $invoice->issue($this->numbering->invoiceNumber(), $now);
            } catch (InvalidBillingData) {
                return null;
            }
            $this->invoices->save($invoice, null);
            $this->audit->record(new AuditEntry(null, 'invoice.issued', 'invoice', $invoice->id, null, [
                'number' => $invoice->number(),
                'total' => $invoice->total()->amount,
                'trigger' => 'encounter_closed',
            ]));

            return new InvoiceIssued($invoice->id, $invoice->encounterId, $invoice->total()->amount, $now);
        });

        if ($issued !== null) {
            $this->events->dispatch($issued);
        }
    }

    private function addItem(string $encounterId, string $patientId, string $sourceType, string $sourceId, string $description, int $quantity, int $unitPriceAmount): void
    {
        $this->tx->run(function () use ($encounterId, $patientId, $sourceType, $sourceId, $description, $quantity, $unitPriceAmount): void {
            $invoice = $this->invoices->lockByEncounter($encounterId) ?? Invoice::draft($this->ids->generate(), $patientId, $encounterId);
            if ($invoice->lifecycle() !== Invoice::DRAFT || $invoice->hasSource($sourceType, $sourceId)) {
                return;
            }
            $invoice->addClinicalItem(InvoiceItem::create($this->ids->generate(), $sourceType, $sourceId, $description, $quantity, Money::rupiah($unitPriceAmount)));
            $this->invoices->save($invoice, null);
            $this->audit->record(new AuditEntry(null, 'invoice.item_added', 'invoice', $invoice->id, null, [
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'quantity' => $quantity,
                'unit_price' => $unitPriceAmount,
            ]));
        });
    }

    private function release(string $encounterId, string $sourceType, string $sourceId): void
    {
        $this->tx->run(function () use ($encounterId, $sourceType, $sourceId): void {
            $invoice = $this->invoices->lockByEncounter($encounterId);
            if ($invoice === null || ! $invoice->removeSourceItem($sourceType, $sourceId)) {
                return;
            }
            $this->invoices->save($invoice, null);
            $this->audit->record(new AuditEntry(null, 'invoice.item_released', 'invoice', $invoice->id, ['source_type' => $sourceType, 'source_id' => $sourceId], null));
        });
    }
}

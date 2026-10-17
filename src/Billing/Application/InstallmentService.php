<?php

declare(strict_types=1);

namespace Src\Billing\Application;

use Src\Billing\Domain\InstallmentPlan;
use Src\Billing\Domain\InstallmentRepository;
use Src\Billing\Domain\InvalidBillingData;
use Src\Billing\Domain\Invoice;
use Src\Billing\Domain\InvoiceRepository;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Transaction;
use Src\Shared\Domain\IdGenerator;

/**
 * KSR-05: jadwal cicilan sisa tagihan. Hanya untuk tagihan terbit yang belum lunas dan satu jadwal per tagihan; jadwal tidak
 * diubah setelah dibuat (koreksi lewat pembayaran dan refund). Izin payment.create diperiksa di Presentation.
 */
final readonly class InstallmentService
{
    public function __construct(
        private InvoiceRepository $invoices,
        private InstallmentRepository $plans,
        private Transaction $tx,
        private AuditLogger $audit,
        private IdGenerator $ids,
    ) {}

    public function create(string $actorId, string $invoiceId, int $count, string $firstDueDate): string
    {
        return $this->tx->run(function () use ($actorId, $invoiceId, $count, $firstDueDate): string {
            $invoice = $this->invoices->lockById($invoiceId) ?? throw new InvalidBillingData('invoice.not_found', 'Tagihan tidak ditemukan.');
            if ($invoice->lifecycle() !== Invoice::ISSUED) {
                throw new InvalidBillingData('installment.not_issued', 'Cicilan hanya untuk tagihan yang sudah diterbitkan dan belum dibatalkan.');
            }
            if ($this->plans->forInvoice($invoiceId) !== null) {
                throw new InvalidBillingData('installment.already_exists', 'Tagihan ini sudah memiliki jadwal cicilan.');
            }

            $plan = InstallmentPlan::create($invoiceId, $invoice->balance(), $invoice->paid(), $count, $firstDueDate, $this->ids);
            $this->plans->save($plan, $actorId);
            $this->audit->record(new AuditEntry($actorId, 'installment.plan_created', 'invoice', $invoiceId, null, [
                'plan_id' => $plan->id,
                'count' => $count,
                'first_due_date' => $firstDueDate,
                'balance' => $invoice->balance()->amount,
            ]));

            return $plan->id;
        });
    }
}

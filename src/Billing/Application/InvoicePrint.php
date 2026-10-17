<?php

declare(strict_types=1);

namespace Src\Billing\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Billing\Domain\InvalidBillingData;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\Clinic\Contracts\LetterheadProvider;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;

/**
 * KSR-06: data cetak invoice dan kwitansi (A4 atau printer termal 80 mm) dengan kop klinik. Membuka tagihan untuk dicetak
 * tercatat di access log lewat InvoiceReader dan di audit. Kwitansi memuat nomor urutnya sendiri per pembayaran.
 */
final readonly class InvoicePrint
{
    public function __construct(
        private InvoiceReader $reader,
        private LetterheadProvider $letterhead,
        private ClinicCalendar $calendar,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  'invoice'|'receipt'  $doc
     * @return array<string, mixed> data untuk templat cetak
     */
    public function data(string $actorId, string $invoiceId, string $doc, ?string $paymentId = null): array
    {
        $detail = $this->reader->detail($invoiceId, 'invoices.print');
        $invoice = $detail['invoice'];
        $payments = $detail['payments'];

        $receipt = null;
        if ($doc === 'receipt') {
            $candidates = array_values(array_filter($payments, fn (array $p) => $p['type'] !== 'refund' && ($paymentId === null || $p['id'] === $paymentId)));
            $receipt = $paymentId === null ? ($candidates === [] ? null : $candidates[count($candidates) - 1]) : ($candidates[0] ?? null);
            if ($receipt === null) {
                throw new InvalidBillingData('payment.not_found', 'Pembayaran untuk kwitansi tidak ditemukan.');
            }
        } elseif ($invoice['number'] === null) {
            throw new InvalidBillingData('invoice.not_issued', 'Tagihan belum diterbitkan; belum ada nomor invoice untuk dicetak.');
        }

        $head = $this->letterhead->forDocument();
        $zone = new DateTimeZone($this->calendar->timezone());
        $local = fn (?string $iso): ?string => $iso === null ? null : (new DateTimeImmutable($iso))->setTimezone($zone)->format('d/m/Y H:i');

        $this->audit->record(new AuditEntry($actorId, $doc === 'receipt' ? 'receipt.printed' : 'invoice.printed', 'invoice', $invoiceId, null, ['payment_id' => $receipt['id'] ?? null]));

        return [
            'doc' => $doc,
            'letterhead' => $head->toArray(),
            'invoice' => [...$invoice, 'issued_at_local' => $local($invoice['issued_at'])],
            'items' => $detail['items'],
            'payments' => array_map(fn (array $p) => [...$p, 'paid_at_local' => $local($p['paid_at'])], $payments),
            'receipt' => $receipt === null ? null : [...$receipt, 'paid_at_local' => $local($receipt['paid_at'])],
        ];
    }
}

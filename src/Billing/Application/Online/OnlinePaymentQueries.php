<?php

declare(strict_types=1);

namespace Src\Billing\Application\Online;

use DateTimeZone;
use Src\Billing\Domain\InvalidBillingData;
use Src\Billing\Domain\InvoiceRepository;
use Src\Billing\Domain\Online\IntentStatus;
use Src\Billing\Domain\Online\OnlinePaymentRepository;
use Src\Billing\Domain\Online\PaymentIntent;
use Src\Clinic\Contracts\ClinicCalendar;

/**
 * P3-05: tampilan QR pembayaran sebuah tagihan dan daftar rekonsiliasi. Hanya membaca.
 */
final readonly class OnlinePaymentQueries
{
    public function __construct(private OnlinePaymentRepository $intents, private InvoiceRepository $invoices, private ClinicCalendar $calendar) {}

    /**
     * @return array<string, mixed>
     */
    public function panel(string $invoiceId): array
    {
        $invoice = $this->invoices->find($invoiceId) ?? throw new InvalidBillingData('invoice.not_found', 'Tagihan tidak ditemukan.');
        $settings = $this->intents->settings();
        $list = $this->intents->forInvoice($invoiceId);
        $active = null;
        foreach ($list as $intent) {
            if ($intent->status === IntentStatus::Pending) {
                $active = $intent;
                break;
            }
        }

        return [
            'invoice' => ['id' => $invoice->id, 'number' => $invoice->number(), 'balance' => $invoice->balance()->amount, 'status' => $invoice->status()->value, 'lifecycle' => $invoice->lifecycle()],
            'ready' => $settings->ready(),
            'active' => $active === null ? null : $this->present($active, true),
            'history' => array_map(fn (PaymentIntent $i) => $this->present($i, false), $list),
        ];
    }

    /**
     * Status ringkas untuk polling halaman QR.
     *
     * @return array{status: string, status_label: string, paid: bool}
     */
    public function status(string $invoiceId, string $intentId): array
    {
        $intent = $this->intents->find($intentId);
        if ($intent === null || $intent->invoiceId !== $invoiceId) {
            throw new InvalidBillingData('online.not_found', 'Pembayaran online tidak ditemukan.');
        }

        return ['status' => $intent->status->value, 'status_label' => $intent->status->label(), 'paid' => $intent->status === IntentStatus::Paid];
    }

    /**
     * @return array{intents: list<array<string, mixed>>}
     */
    public function desk(): array
    {
        $zone = new DateTimeZone($this->calendar->timezone());

        return ['intents' => array_map(fn (array $r) => [
            'id' => $r['id'], 'invoice_id' => $r['invoice_id'], 'invoice_number' => $r['invoice_number'], 'order_id' => $r['order_id'], 'amount' => $r['amount'], 'status' => $r['status'],
            'status_label' => $r['status_label'], 'note' => $r['note'], 'gateway_ref' => $r['gateway_ref'],
            'created_at' => $r['created_at']->setTimezone($zone)->format('Y-m-d H:i'), 'paid_at' => $r['paid_at']?->setTimezone($zone)->format('Y-m-d H:i'),
        ], $this->intents->listForDesk(200))];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(PaymentIntent $i, bool $withQr): array
    {
        $zone = new DateTimeZone($this->calendar->timezone());

        return [
            'id' => $i->id, 'amount' => $i->amount, 'status' => $i->status->value, 'status_label' => $i->status->label(), 'note' => $i->note,
            'expires_at' => $i->expiresAt->setTimezone($zone)->format('Y-m-d H:i'), 'paid_at' => $i->paidAt?->setTimezone($zone)->format('Y-m-d H:i'),
            ...($withQr ? ['qr_image' => $i->qrImage, 'qr_string' => $i->qrString] : []),
        ];
    }
}

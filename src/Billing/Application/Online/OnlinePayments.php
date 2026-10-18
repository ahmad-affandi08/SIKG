<?php

declare(strict_types=1);

namespace Src\Billing\Application\Online;

use DateInterval;
use Src\Billing\Application\InvoiceService;
use Src\Billing\Domain\Events\InvoiceVoided;
use Src\Billing\Domain\InvalidBillingData;
use Src\Billing\Domain\InvoiceRepository;
use Src\Billing\Domain\Online\IntentStatus;
use Src\Billing\Domain\Online\OnlinePaymentRepository;
use Src\Billing\Domain\Online\PaymentIntent;
use Src\Billing\Domain\PaymentType;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\ResourceLock;
use Src\Shared\Application\TenantContext;
use Src\Shared\Application\Transaction;
use Src\Shared\Domain\IdGenerator;

/**
 * P3-05: pembayaran online (QRIS dinamis lewat payment gateway). Kasir membuat pembayaran untuk sisa tagihan; pasien membayar lewat halaman QRIS DOKU dengan aplikasi
 * pembayarannya; penyedia memberi tahu lewat webhook dan rekonsiliasi terjadwal memeriksa yang terlewat. Webhook tidak dipercaya
 * begitu saja: tanda tangannya diperiksa lalu status ditanyakan ulang ke penyedia, dan pembayaran dicatat lewat alur pembayaran biasa
 * dengan kunci idempotensi per niat (M7), sehingga notifikasi ganda tidak mencatat dua kali. Uang yang diterima tetapi tidak dapat
 * dicatat (tagihan lunas atau batal, jumlah beda) ditandai `Mismatch` untuk diselesaikan petugas.
 */
final readonly class OnlinePayments
{
    public const RECONCILE_BATCH = 100;

    public function __construct(
        private OnlinePaymentRepository $intents,
        private PaymentGateway $gateway,
        private InvoiceRepository $invoices,
        private InvoiceService $payments,
        private TenantContext $tenant,
        private ResourceLock $locks,
        private Transaction $tx,
        private AuditLogger $audit,
        private IdGenerator $ids,
        private Clock $clock,
    ) {}

    /**
     * Membuat QR untuk sisa tagihan (atau sebagian). Bila tagihan sudah punya QR aktif yang belum kedaluwarsa, QR itu dikembalikan.
     *
     * @return string id niat bayar
     */
    public function start(string $actorId, string $invoiceId, ?int $amount): string
    {
        $settings = $this->intents->settings();
        if (! $settings->ready()) {
            throw new InvalidBillingData('online.not_ready', 'Pembayaran online belum diaktifkan atau belum lengkap pengaturannya.');
        }

        $created = $this->tx->run(function () use ($actorId, $invoiceId, $amount, $settings): PaymentIntent {
            $this->locks->acquire("invoice-online:{$invoiceId}");
            $invoice = $this->invoices->find($invoiceId) ?? throw new InvalidBillingData('invoice.not_found', 'Tagihan tidak ditemukan.');
            if ($invoice->lifecycle() !== 'issued') {
                throw new InvalidBillingData('online.invoice_not_payable', 'Hanya tagihan yang sudah diterbitkan yang dapat dibayar online.');
            }
            $balance = $invoice->balance()->amount;
            if ($balance < 1) {
                throw new InvalidBillingData('invoice.already_paid', 'Tagihan sudah lunas.');
            }
            $value = $amount ?? $balance;
            if ($value < 1 || $value > $balance) {
                throw new InvalidBillingData('online.invalid_amount', 'Jumlah harus lebih dari nol dan tidak melebihi sisa tagihan.');
            }

            $now = $this->clock->now();
            $active = $this->intents->activeForInvoice($invoiceId);
            if ($active !== null) {
                if ($active->expiresAt > $now && $active->amount === $value && $active->paymentUrl !== null) {
                    return $active;
                }
                try {
                    $this->gateway->cancel($settings, $active->orderId);
                } catch (PaymentGatewayFailure) {
                    // Pembayaran yang telanjur masuk ke QR lama dicatat atau ditandai Mismatch saat rekonsiliasi.
                }
                $this->closePending($active, IntentStatus::Cancelled, 'Diganti QR baru.');
            }

            $id = $this->ids->generate();
            $intent = new PaymentIntent($id, $invoiceId, 'SIKG-'.$id, $value, IntentStatus::Pending, null, null, $now->add(new DateInterval('PT'.$settings->expiryMinutes.'M')), null, null, null, $actorId);
            $this->intents->add($intent, $actorId);
            $this->audit->record(new AuditEntry($actorId, 'online_payment.started', 'invoice', $invoiceId, null, ['intent_id' => $id, 'amount' => $value]));

            return $intent;
        });

        if ($created->paymentUrl !== null || $created->gatewayRef !== null) {
            return $created->id;
        }
        try {
            $charge = $this->gateway->charge($settings, $created->orderId, $created->amount, $settings->expiryMinutes);
        } catch (PaymentGatewayFailure $e) {
            $this->tx->run(fn () => $this->intents->transition($created->id, IntentStatus::Failed, $e->getMessage(), null, null));

            throw new InvalidBillingData('online.gateway_failed', $e->getMessage());
        }
        $this->tx->run(fn () => $this->intents->attachCharge($created->id, $charge->paymentUrl, $charge->reference, $created->expiresAt));

        return $created->id;
    }

    /**
     * Membatalkan QR yang masih menunggu. Bila ternyata sudah dibayar, pembayarannya dicatat dan pembatalan ditolak.
     */
    public function cancel(string $actorId, string $intentId): void
    {
        $settings = $this->intents->settings();
        $intent = $this->intents->find($intentId) ?? throw new InvalidBillingData('online.not_found', 'Pembayaran online tidak ditemukan.');
        if ($intent->status !== IntentStatus::Pending) {
            throw new InvalidBillingData('online.not_pending', 'Pembayaran online ini sudah tidak menunggu.');
        }
        try {
            $status = $this->gateway->status($settings, $intent->orderId);
        } catch (PaymentGatewayFailure $e) {
            throw new InvalidBillingData('online.gateway_failed', $e->getMessage());
        }
        if ($status->state === IntentStatus::Paid) {
            $this->apply($intent->id, $status);

            throw new InvalidBillingData('online.already_paid_at_gateway', 'QR ini sudah dibayar pasien; pembayarannya dicatat ke tagihan.');
        }
        try {
            $this->gateway->cancel($settings, $intent->orderId);
        } catch (PaymentGatewayFailure $e) {
            throw new InvalidBillingData('online.gateway_failed', $e->getMessage());
        }
        $this->tx->run(function () use ($actorId, $intent): void {
            $locked = $this->intents->lockForUpdate($intent->id);
            if ($locked !== null && $locked->status === IntentStatus::Pending) {
                $this->closePending($locked, IntentStatus::Cancelled, 'Dibatalkan oleh petugas.');
                $this->audit->record(new AuditEntry($actorId, 'online_payment.cancelled', 'invoice', $intent->invoiceId, null, ['intent_id' => $intent->id]));
            }
        });
    }

    /**
     * Notifikasi webhook penyedia. Klinik dikenali dari token pada alamat. Mengembalikan false bila tanda tangan tidak sah.
     *
     * @param  array<string, string>  $headers  nama header huruf kecil => nilai
     * @param  string  $rawBody  isi mentah permintaan (dasar digest tanda tangan)
     * @param  string  $path  alamat webhook tanpa host, contoh `/webhooks/payments/{token}`
     * @return bool|null null bila token tidak dikenal
     */
    public function handleWebhook(string $token, array $headers, string $rawBody, string $path): ?bool
    {
        $clinicId = $this->intents->clinicForWebhookToken($token);
        if ($clinicId === null) {
            return null;
        }

        return (bool) $this->tenant->runAs($clinicId, null, function () use ($headers, $rawBody, $path): bool {
            $settings = $this->intents->settings();
            if (! $this->gateway->verifyNotification($settings, $headers, $rawBody, $path)) {
                return false;
            }
            $payload = json_decode($rawBody, true);
            $orderId = is_array($payload) && is_array($payload['order'] ?? null) ? ($payload['order']['invoice_number'] ?? null) : null;
            $intent = is_string($orderId) ? $this->intents->findByOrderId($orderId) : null;
            if ($intent === null) {
                return true;
            }
            // Status ditanyakan ulang ke penyedia: badan webhook tidak menjadi sumber kebenaran. Galat sementara dilempar agar penyedia mencoba lagi.
            $status = $this->gateway->status($settings, $intent->orderId);
            $this->apply($intent->id, $status);

            return true;
        });
    }

    /**
     * Rekonsiliasi untuk satu klinik (konteks sudah diset): memeriksa niat yang masih menunggu ke penyedia.
     *
     * @return array{checked: int, paid: int, closed: int, failed: int}
     */
    public function reconcile(): array
    {
        $settings = $this->intents->settings();
        $out = ['checked' => 0, 'paid' => 0, 'closed' => 0, 'failed' => 0];
        if ($settings->secretKey === null) {
            return $out;
        }
        foreach ($this->intents->pending(self::RECONCILE_BATCH) as $intent) {
            $out['checked']++;
            try {
                $status = $this->gateway->status($settings, $intent->orderId);
            } catch (PaymentGatewayFailure) {
                $out['failed']++;

                continue;
            }
            $result = $this->apply($intent->id, $status);
            if ($result === IntentStatus::Paid) {
                $out['paid']++;
            } elseif ($result !== null && $result !== IntentStatus::Pending) {
                $out['closed']++;
            }
        }

        return $out;
    }

    /**
     * Pendengar InvoiceVoided: QR yang masih menunggu dibatalkan. Bila pasien sempat membayar, notifikasinya kemudian ditandai Mismatch.
     */
    public function invoiceVoided(InvoiceVoided $event): void
    {
        $settings = $this->intents->settings();
        $this->tx->run(function () use ($event, $settings): void {
            $intent = $this->intents->activeForInvoice($event->invoiceId);
            if ($intent === null) {
                return;
            }
            try {
                $this->gateway->cancel($settings, $intent->orderId);
            } catch (PaymentGatewayFailure) {
                // Dibatalkan di sisi kami; pembayaran yang telanjur masuk akan ditandai Mismatch saat rekonsiliasi.
            }
            $this->closePending($intent, IntentStatus::Cancelled, 'Tagihan dibatalkan.');
        });
    }

    /**
     * Menerapkan status penyedia ke niat bayar secara idempoten.
     *
     * @return IntentStatus|null status akhir niat, null bila tidak ditemukan
     */
    private function apply(string $intentId, GatewayStatus $gateway): ?IntentStatus
    {
        return $this->tx->run(function () use ($intentId, $gateway): ?IntentStatus {
            $intent = $this->intents->lockForUpdate($intentId);
            if ($intent === null) {
                return null;
            }
            if ($intent->status === IntentStatus::Paid || $intent->status === IntentStatus::Mismatch) {
                return $intent->status;
            }
            $now = $this->clock->now();

            if ($gateway->state === IntentStatus::Paid) {
                return $this->recordPaid($intent, $gateway);
            }
            if ($intent->status !== IntentStatus::Pending) {
                return $intent->status;
            }
            if ($gateway->state === IntentStatus::Pending) {
                if ($intent->expiresAt <= $now) {
                    $this->closePending($intent, IntentStatus::Expired, 'Melewati masa berlaku QR.');

                    return IntentStatus::Expired;
                }
                $this->intents->touchChecked($intent->id, $now);

                return IntentStatus::Pending;
            }
            $this->closePending($intent, $gateway->state, $gateway->state === IntentStatus::Failed ? 'Ditolak penyedia pembayaran.' : null);

            return $gateway->state;
        });
    }

    private function recordPaid(PaymentIntent $intent, GatewayStatus $gateway): IntentStatus
    {
        $now = $this->clock->now();
        $settings = $this->intents->settings();
        if ($gateway->amount !== $intent->amount) {
            $this->intents->transition($intent->id, IntentStatus::Mismatch, "Jumlah dari penyedia ({$gateway->amount}) berbeda dari QR ({$intent->amount}).", $now, null);
            $this->audit->record(new AuditEntry(null, 'online_payment.mismatch', 'invoice', $intent->invoiceId, null, ['intent_id' => $intent->id, 'reason' => 'amount']));

            return IntentStatus::Mismatch;
        }
        try {
            if ($settings->methodId === null || $intent->createdBy === null) {
                throw new InvalidBillingData('payment.method_not_found', 'Metode pembayaran online belum dipilih.');
            }
            $payment = $this->payments->recordPayment($intent->createdBy, $intent->invoiceId, $settings->methodId, PaymentType::Pay, $intent->amount, 'online:'.$intent->id, $gateway->reference ?? $intent->orderId);
        } catch (InvalidBillingData $e) {
            $this->intents->transition($intent->id, IntentStatus::Mismatch, $e->getMessage(), $now, null);
            $this->audit->record(new AuditEntry(null, 'online_payment.mismatch', 'invoice', $intent->invoiceId, null, ['intent_id' => $intent->id, 'reason' => $e->businessCode()]));

            return IntentStatus::Mismatch;
        }
        $this->intents->transition($intent->id, IntentStatus::Paid, null, $now, $payment->id);
        $this->audit->record(new AuditEntry(null, 'online_payment.paid', 'invoice', $intent->invoiceId, null, ['intent_id' => $intent->id, 'payment_id' => $payment->id, 'amount' => $intent->amount]));

        return IntentStatus::Paid;
    }

    private function closePending(PaymentIntent $intent, IntentStatus $status, ?string $note): void
    {
        $this->intents->transition($intent->id, $status, $note, null, null);
    }
}

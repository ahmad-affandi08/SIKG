<?php

declare(strict_types=1);

namespace Src\Billing\Application\Online;

use Src\Billing\Domain\InvalidBillingData;
use Src\Billing\Domain\Online\OnlinePaymentRepository;
use Src\Billing\Domain\Online\OnlinePaymentSettings;
use Src\Clinic\Contracts\PaymentMethodCatalog;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Transaction;

/**
 * P3-05: pengaturan pembayaran online. Server key hanya ditulis (terenkripsi) dan tidak pernah ditampilkan kembali (M13).
 */
final readonly class OnlinePaymentAdministration
{
    public function __construct(private OnlinePaymentRepository $intents, private PaymentMethodCatalog $methods, private AuditLogger $audit, private Transaction $tx) {}

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $s = $this->intents->settings();

        return [
            'enabled' => $s->enabled, 'environment' => $s->environment, 'merchant_id' => $s->merchantId ?? '', 'has_key' => $s->serverKey !== null && $s->serverKey !== '', 'payment_method_id' => $s->methodId ?? '',
            'expiry_minutes' => $s->expiryMinutes, 'webhook_path' => '/webhooks/payments/'.$s->webhookToken,
            'methods' => array_map(fn ($m) => ['value' => $m->id, 'label' => $m->name], $this->methods->active()),
        ];
    }

    /**
     * @param  string|null  $serverKey  null atau kosong berarti kunci lama dipertahankan
     */
    public function save(string $actorId, bool $enabled, string $environment, ?string $merchantId, ?string $serverKey, ?string $methodId, int $expiryMinutes): void
    {
        OnlinePaymentSettings::assertValid($environment, $expiryMinutes);
        $serverKey = $serverKey === null || trim($serverKey) === '' ? null : trim($serverKey);
        $this->tx->run(function () use ($actorId, $enabled, $environment, $merchantId, $serverKey, $methodId, $expiryMinutes): void {
            if ($methodId !== null) {
                $method = $this->methods->find($methodId);
                if ($method === null || ! $method->active) {
                    throw new InvalidBillingData('payment.method_not_found', 'Metode pembayaran tidak ditemukan atau nonaktif.');
                }
            }
            $before = $this->intents->settings();
            if ($enabled && ($methodId === null || ($serverKey === null && ($before->serverKey === null || $before->serverKey === '')))) {
                throw new InvalidBillingData('online.settings_incomplete', 'Isi server key dan pilih metode pembayaran sebelum mengaktifkan pembayaran online.');
            }
            $this->intents->saveSettings($actorId, $enabled, $environment, $merchantId === null || trim($merchantId) === '' ? null : trim($merchantId), $serverKey, $methodId, $expiryMinutes);
            // Server key tidak pernah masuk audit; hanya penanda bahwa kunci diganti.
            $this->audit->record(new AuditEntry($actorId, 'online_payment.settings_changed', 'clinic', 'online-payments', [
                'enabled' => $before->enabled, 'environment' => $before->environment, 'method_id' => $before->methodId, 'expiry_minutes' => $before->expiryMinutes,
            ], ['enabled' => $enabled, 'environment' => $environment, 'method_id' => $methodId, 'expiry_minutes' => $expiryMinutes, 'key_replaced' => $serverKey !== null]));
        });
    }
}

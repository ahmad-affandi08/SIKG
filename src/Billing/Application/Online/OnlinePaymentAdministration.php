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
 * P3-05: pengaturan pembayaran online. Secret key hanya ditulis (terenkripsi) dan tidak pernah ditampilkan kembali (M13).
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
            'enabled' => $s->enabled, 'environment' => $s->environment, 'client_id' => $s->clientId ?? '', 'has_key' => $s->secretKey !== null && $s->secretKey !== '', 'payment_method_id' => $s->methodId ?? '',
            'expiry_minutes' => $s->expiryMinutes, 'webhook_path' => '/webhooks/payments/'.$s->webhookToken,
            'methods' => array_map(fn ($m) => ['value' => $m->id, 'label' => $m->name], $this->methods->active()),
        ];
    }

    /**
     * Mengganti token pada alamat webhook (mis. bila alamat lama bocor). Alamat lama langsung ditolak; alamat baru harus didaftarkan
     * ulang sebagai Notification URL di dasbor penyedia. Token tidak dicatat di audit.
     */
    public function rotateWebhookToken(string $actorId): void
    {
        $this->tx->run(function () use ($actorId): void {
            $this->intents->rotateWebhookToken($actorId);
            $this->audit->record(new AuditEntry($actorId, 'online_payment.webhook_token_rotated', 'clinic', 'online-payments', null, null));
        });
    }

    /**
     * @param  string|null  $secretKey  null atau kosong berarti kunci lama dipertahankan
     */
    public function save(string $actorId, bool $enabled, string $environment, ?string $clientId, ?string $secretKey, ?string $methodId, int $expiryMinutes): void
    {
        OnlinePaymentSettings::assertValid($environment, $expiryMinutes);
        $secretKey = $secretKey === null || trim($secretKey) === '' ? null : trim($secretKey);
        $this->tx->run(function () use ($actorId, $enabled, $environment, $clientId, $secretKey, $methodId, $expiryMinutes): void {
            if ($methodId !== null) {
                $method = $this->methods->find($methodId);
                if ($method === null || ! $method->active) {
                    throw new InvalidBillingData('payment.method_not_found', 'Metode pembayaran tidak ditemukan atau nonaktif.');
                }
            }
            $before = $this->intents->settings();
            $hasKey = ($secretKey !== null && $secretKey !== '') || ($before->secretKey !== null && $before->secretKey !== '');
            $hasClient = ($clientId !== null && trim($clientId) !== '') || ($before->clientId !== null && $before->clientId !== '');
            if ($enabled && ($methodId === null || ! $hasKey || ! $hasClient)) {
                throw new InvalidBillingData('online.settings_incomplete', 'Isi Client ID, Secret Key, dan pilih metode pembayaran sebelum mengaktifkan pembayaran online.');
            }
            $this->intents->saveSettings($actorId, $enabled, $environment, $clientId === null || trim($clientId) === '' ? null : trim($clientId), $secretKey, $methodId, $expiryMinutes);
            // Secret key tidak pernah masuk audit; hanya penanda bahwa kunci diganti.
            $this->audit->record(new AuditEntry($actorId, 'online_payment.settings_changed', 'clinic', 'online-payments', [
                'enabled' => $before->enabled, 'environment' => $before->environment, 'method_id' => $before->methodId, 'expiry_minutes' => $before->expiryMinutes,
            ], ['enabled' => $enabled, 'environment' => $environment, 'method_id' => $methodId, 'expiry_minutes' => $expiryMinutes, 'key_replaced' => $secretKey !== null]));
        });
    }
}

<?php

declare(strict_types=1);

namespace Src\Billing\Domain\Online;

use Src\Billing\Domain\InvalidBillingData;

/**
 * Pengaturan pembayaran online satu klinik. `serverKey` berisi nilai terdekripsi dan tidak boleh ditulis ke log atau layar.
 */
final readonly class OnlinePaymentSettings
{
    public const PROVIDER_MIDTRANS = 'midtrans';

    public const ENV_SANDBOX = 'sandbox';

    public const ENV_PRODUCTION = 'production';

    public function __construct(
        public bool $enabled,
        public string $provider,
        public string $environment,
        public ?string $merchantId,
        public ?string $serverKey,
        public ?string $methodId,
        public int $expiryMinutes,
        public string $webhookToken,
    ) {}

    public function ready(): bool
    {
        return $this->enabled && $this->serverKey !== null && $this->serverKey !== '' && $this->methodId !== null;
    }

    /**
     * @throws InvalidBillingData
     */
    public static function assertValid(string $environment, int $expiryMinutes): void
    {
        if (! in_array($environment, [self::ENV_SANDBOX, self::ENV_PRODUCTION], true)) {
            throw new InvalidBillingData('online.settings_invalid', 'Lingkungan harus sandbox atau production.');
        }
        if ($expiryMinutes < 5 || $expiryMinutes > 1440) {
            throw new InvalidBillingData('online.settings_invalid', 'Masa berlaku QR 5 sampai 1440 menit.');
        }
    }
}

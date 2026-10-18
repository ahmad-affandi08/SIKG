<?php

declare(strict_types=1);

namespace Src\Billing\Application\Online;

/**
 * Hasil membuat pembayaran di penyedia: referensi penyedia (bila ada) dan tautan halaman pembayaran (QRIS) yang dihosting penyedia.
 */
final readonly class GatewayCharge
{
    public function __construct(public ?string $reference, public string $paymentUrl) {}
}

<?php

declare(strict_types=1);

namespace Src\Billing\Application\Online;

use RuntimeException;

/**
 * Galat saat berbicara dengan penyedia pembayaran. Pesannya sudah dibersihkan dari kunci, nomor, dan nama.
 */
final class PaymentGatewayFailure extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable)
    {
        parent::__construct($message);
    }
}

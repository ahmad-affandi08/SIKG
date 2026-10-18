<?php

declare(strict_types=1);

namespace Src\Billing\Application\Online;

use Src\Billing\Domain\Online\IntentStatus;

/**
 * Status transaksi dari penyedia, sudah dipetakan: Pending, Paid, Expired, Cancelled, atau Failed. `amount` rupiah bulat.
 */
final readonly class GatewayStatus
{
    public function __construct(public IntentStatus $state, public int $amount, public ?string $reference) {}
}

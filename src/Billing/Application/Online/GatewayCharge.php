<?php

declare(strict_types=1);

namespace Src\Billing\Application\Online;

final readonly class GatewayCharge
{
    public function __construct(public ?string $reference, public ?string $qrString, public ?string $qrImage) {}
}

<?php

declare(strict_types=1);

namespace Src\Billing\Domain;

enum DiscountKind: string
{
    case Percent = 'percent';
    case Amount = 'amount';
}

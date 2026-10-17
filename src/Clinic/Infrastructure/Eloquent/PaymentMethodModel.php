<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure\Eloquent;

use Src\Shared\Infrastructure\Eloquent\TenantMasterModel;

final class PaymentMethodModel extends TenantMasterModel
{
    protected $table = 'payment_methods';

    /** @var list<string> */
    protected $fillable = ['name', 'type', 'branch_id'];
}

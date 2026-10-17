<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure\Eloquent;

use Src\Shared\Infrastructure\Eloquent\TenantMasterModel;

final class ProcedureModel extends TenantMasterModel
{
    protected $table = 'procedures';

    /** @var list<string> */
    protected $fillable = ['code', 'name', 'category', 'scope', 'default_price_amount', 'branch_id'];
}

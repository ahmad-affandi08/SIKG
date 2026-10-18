<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure\Eloquent;

use Src\Shared\Infrastructure\Eloquent\TenantMasterModel;

/**
 * Cabang klinik (P3-04). Tabel cabang tidak punya `branch_id`; dibatasi per klinik saja (`TenantMasterModel`).
 *
 * @property string $name
 * @property string|null $address
 */
final class BranchModel extends TenantMasterModel
{
    protected $table = 'branches';

    /** @var list<string> */
    protected $fillable = ['name', 'address', 'is_active'];
}

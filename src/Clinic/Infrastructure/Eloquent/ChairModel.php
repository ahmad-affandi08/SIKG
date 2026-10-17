<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure\Eloquent;

use Src\Shared\Infrastructure\Eloquent\TenantMasterModel;

/**
 * @property string $name
 */
final class ChairModel extends TenantMasterModel
{
    protected $table = 'chairs';

    /** @var list<string> */
    protected $fillable = ['name', 'branch_id'];
}

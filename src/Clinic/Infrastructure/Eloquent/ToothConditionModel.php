<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure\Eloquent;

use Src\Shared\Infrastructure\Eloquent\TenantMasterModel;

/**
 * @property string $code
 * @property string $name
 * @property string $color
 * @property string $scope
 */
final class ToothConditionModel extends TenantMasterModel
{
    protected $table = 'tooth_conditions';

    /** @var list<string> */
    protected $fillable = ['code', 'name', 'color', 'scope', 'branch_id'];
}

<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure\Eloquent;

use Src\Shared\Infrastructure\Eloquent\TenantMasterModel;

/**
 * @property string $code
 * @property string $name
 * @property string|null $category
 * @property string $scope
 * @property int $default_price_amount
 * @property string|null $result_condition_code
 * @property string|null $icd9_code
 */
final class ProcedureModel extends TenantMasterModel
{
    protected $table = 'procedures';

    /** @var list<string> */
    protected $fillable = ['code', 'name', 'category', 'scope', 'default_price_amount', 'result_condition_code', 'icd9_code', 'branch_id'];
}

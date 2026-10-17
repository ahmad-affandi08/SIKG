<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure\Eloquent;

use Src\Shared\Infrastructure\Eloquent\TenantMasterModel;

/**
 * @property string $name
 */
final class ExpenseCategoryModel extends TenantMasterModel
{
    protected $table = 'expense_categories';

    /** @var list<string> */
    protected $fillable = ['name', 'branch_id'];
}

<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * @property string $id
 * @property Carbon $expense_date
 * @property string $category_id
 * @property int $amount
 * @property string|null $payee
 * @property string $description
 * @property string|null $reference_no
 * @property string $status
 * @property string|null $void_reason
 */
final class ExpenseModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'expenses';

    /** @var list<string> */
    protected $fillable = ['branch_id'];

    protected static function branchScoped(): bool
    {
        return false;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['expense_date' => 'date', 'amount' => 'integer', 'voided_at' => 'datetime'];
    }
}

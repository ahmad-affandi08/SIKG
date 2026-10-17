<?php

declare(strict_types=1);

namespace Src\Pharmacy\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * @property string $id
 * @property string $medicine_id
 * @property int $quantity
 */
final class StockBalanceModel extends Model
{
    use BelongsToClinic;
    use UsesUlid;

    protected $table = 'stock_balances';

    /** @var list<string> */
    protected $fillable = ['medicine_id', 'quantity', 'branch_id'];

    protected static function branchScoped(): bool
    {
        return false;
    }
}

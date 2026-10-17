<?php

declare(strict_types=1);

namespace Src\Pharmacy\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * P2-05: baris pesanan pembelian.
 *
 * @property string $id
 * @property string $purchase_order_id
 * @property string $medicine_id
 * @property int $quantity_ordered
 * @property int $quantity_received
 * @property int $unit_cost_amount
 */
final class PurchaseOrderItemModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'purchase_order_items';

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
        return ['quantity_ordered' => 'integer', 'quantity_received' => 'integer', 'unit_cost_amount' => 'integer'];
    }
}

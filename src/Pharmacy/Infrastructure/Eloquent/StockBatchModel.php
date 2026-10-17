<?php

declare(strict_types=1);

namespace Src\Pharmacy\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * P2-05: batch stok dengan sisa; hanya sisa yang berubah, selebihnya hanya-baca.
 *
 * @property string $id
 * @property string $medicine_id
 * @property string|null $batch_no
 * @property Carbon|null $expiry_date
 * @property int $received_quantity
 * @property int $remaining_quantity
 * @property int|null $unit_cost_amount
 * @property string|null $supplier_id
 * @property string|null $purchase_order_id
 * @property Carbon $received_at
 */
final class StockBatchModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'stock_batches';

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
        return ['received_quantity' => 'integer', 'remaining_quantity' => 'integer', 'unit_cost_amount' => 'integer', 'expiry_date' => 'date', 'received_at' => 'datetime'];
    }
}

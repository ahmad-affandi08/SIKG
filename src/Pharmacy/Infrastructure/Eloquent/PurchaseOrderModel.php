<?php

declare(strict_types=1);

namespace Src\Pharmacy\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * P2-05: pesanan pembelian ke pemasok.
 *
 * @property string $id
 * @property string $po_no
 * @property string $supplier_id
 * @property string $status
 * @property Carbon|null $ordered_at
 * @property Carbon|null $expected_date
 * @property string|null $note
 * @property int $version
 */
final class PurchaseOrderModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'purchase_orders';

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
        return ['version' => 'integer', 'ordered_at' => 'datetime', 'expected_date' => 'date'];
    }
}

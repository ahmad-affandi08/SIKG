<?php

declare(strict_types=1);

namespace Src\Pharmacy\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * @property string $id
 * @property string $medicine_id
 * @property string|null $branch_id
 * @property int $quantity
 */
final class StockBalanceModel extends Model
{
    use BelongsToClinic;
    use UsesUlid;

    protected $table = 'stock_balances';

    /** @var list<string> */
    protected $fillable = ['medicine_id', 'quantity', 'branch_id'];

    /**
     * Saldo per obat dalam cakupan cabang aktif: satu cabang bila konteks terikat cabang, jumlah seluruh cabang bila tidak (P3-04).
     *
     * @param  list<string>|null  $medicineIds
     * @return Collection<string, int>
     */
    public static function totals(?array $medicineIds = null): Collection
    {
        $query = self::query()->selectRaw('medicine_id, SUM(quantity) as total')->groupBy('medicine_id');
        if ($medicineIds !== null) {
            $query->whereIn('medicine_id', $medicineIds);
        }

        return $query->get()->mapWithKeys(fn ($row) => [(string) $row->medicine_id => (int) $row->getAttribute('total')]);
    }
}

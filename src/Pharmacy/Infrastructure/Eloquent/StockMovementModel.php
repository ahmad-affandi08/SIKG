<?php

declare(strict_types=1);

namespace Src\Pharmacy\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * Mutasi stok hanya bertambah: tidak diubah atau dihapus; koreksi berupa mutasi penyesuaian.
 *
 * @property string $id
 * @property string $medicine_id
 * @property string $type
 * @property int $quantity
 * @property int $balance_after
 * @property string|null $reference_type
 * @property string|null $reference_id
 * @property string|null $reason
 * @property string|null $batch_no
 * @property Carbon|null $expiry_date
 * @property string|null $created_by
 * @property Carbon $created_at
 */
final class StockMovementModel extends Model
{
    use BelongsToClinic;
    use UsesUlid;

    public const UPDATED_AT = null;

    protected $table = 'stock_movements';

    /** @var list<string> */
    protected $fillable = ['medicine_id', 'type', 'quantity', 'balance_after', 'reference_type', 'reference_id', 'reason', 'batch_no', 'expiry_date', 'created_by', 'branch_id'];

    protected static function booted(): void
    {
        $refuse = function (): never {
            throw new LogicException('Mutasi stok tidak dapat diubah atau dihapus; buat mutasi penyesuaian.');
        };
        self::updating($refuse);
        self::deleting($refuse);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['expiry_date' => 'date', 'created_at' => 'datetime'];
    }
}

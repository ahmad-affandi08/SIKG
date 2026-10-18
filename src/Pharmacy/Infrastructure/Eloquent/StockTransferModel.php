<?php

declare(strict_types=1);

namespace Src\Pharmacy\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * P3-04: transfer stok antar cabang. Hanya bertambah: tidak diubah atau dihapus (M5); koreksi berupa transfer balik.
 *
 * @property string $id
 * @property string $medicine_id
 * @property string|null $from_branch_id
 * @property string|null $to_branch_id
 * @property int $quantity
 * @property string|null $reason
 * @property string|null $created_by
 * @property Carbon $created_at
 */
final class StockTransferModel extends Model
{
    use BelongsToClinic;
    use UsesUlid;

    public const UPDATED_AT = null;

    protected $table = 'stock_transfers';

    /** @var list<string> */
    protected $fillable = ['branch_id', 'from_branch_id', 'to_branch_id', 'medicine_id', 'quantity', 'reason', 'created_by'];

    /** Transfer terlihat oleh cabang asal dan tujuan; pembatasan cabang ada di repositori. */
    protected static function branchScoped(): bool
    {
        return false;
    }

    protected static function booted(): void
    {
        $refuse = function (): never {
            throw new LogicException('Transfer stok tidak dapat diubah atau dihapus; buat transfer balik.');
        };
        self::updating($refuse);
        self::deleting($refuse);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'integer', 'created_at' => 'datetime'];
    }
}

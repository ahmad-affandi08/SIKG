<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * Tutup kas bersifat final: tidak diubah dan tidak dihapus.
 *
 * @property string $id
 * @property Carbon $closing_date
 * @property string $cashier_id
 * @property int $system_amount
 * @property int $counted_amount
 * @property int $difference_amount
 * @property string|null $note
 * @property Carbon $closed_at
 */
final class CashClosingModel extends Model
{
    use BelongsToClinic;
    use UsesUlid;

    public const UPDATED_AT = null;

    protected $table = 'cash_closings';

    /** @var list<string> */
    protected $fillable = ['branch_id'];

    protected static function booted(): void
    {
        self::updating(function (): void {
            throw new LogicException('Tutup kas tidak dapat diubah.');
        });
        self::deleting(function (): void {
            throw new LogicException('Tutup kas tidak dapat dihapus.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['closing_date' => 'date', 'closed_at' => 'datetime'];
    }
}

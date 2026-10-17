<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * Jadwal cicilan tidak diubah dan tidak dihapus setelah dibuat (M5).
 *
 * @property string $id
 * @property string $invoice_id
 * @property int $installment_count
 * @property int $base_paid_amount
 */
final class InstallmentPlanModel extends Model
{
    use BelongsToClinic;
    use UsesUlid;

    public const UPDATED_AT = null;

    protected $table = 'installment_plans';

    /** @var list<string> */
    protected $fillable = ['branch_id'];

    protected static function booted(): void
    {
        self::updating(function (): void {
            throw new LogicException('Jadwal cicilan tidak dapat diubah.');
        });
        self::deleting(function (): void {
            throw new LogicException('Jadwal cicilan tidak dapat dihapus.');
        });
    }
}

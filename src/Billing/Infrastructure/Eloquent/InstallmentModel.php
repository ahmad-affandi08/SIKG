<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * @property string $id
 * @property string $plan_id
 * @property string $invoice_id
 * @property int $sequence
 * @property Carbon $due_date
 * @property int $amount
 */
final class InstallmentModel extends Model
{
    use BelongsToClinic;
    use UsesUlid;

    public const UPDATED_AT = null;

    protected $table = 'installments';

    /** @var list<string> */
    protected $fillable = ['branch_id'];

    protected static function booted(): void
    {
        self::updating(function (): void {
            throw new LogicException('Cicilan tidak dapat diubah.');
        });
        self::deleting(function (): void {
            throw new LogicException('Cicilan tidak dapat dihapus.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }
}

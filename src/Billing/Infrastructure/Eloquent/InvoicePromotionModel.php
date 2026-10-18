<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * P3-06: promosi pada tagihan.
 *
 * @property string $id
 * @property string $invoice_id
 * @property string $patient_id
 * @property string $type
 * @property string|null $voucher_id
 * @property int $points_used
 * @property int $discount_amount
 * @property string $status
 * @property int|null $active_slot
 */
final class InvoicePromotionModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'invoice_promotions';

    /** @var list<string> */
    protected $fillable = ['branch_id'];

    protected static function branchScoped(): bool
    {
        return false;
    }
}

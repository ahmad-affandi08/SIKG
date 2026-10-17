<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * @property string $id
 * @property string $invoice_id
 * @property string $source_type
 * @property string|null $source_id
 * @property string $description
 * @property int $quantity
 * @property int $unit_price_amount
 * @property string|null $discount_type
 * @property int|null $discount_value
 * @property string|null $discount_reason
 * @property int $discount_amount
 * @property int $total_amount
 */
final class InvoiceItemModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'invoice_items';

    /** @var list<string> */
    protected $fillable = ['branch_id'];
}

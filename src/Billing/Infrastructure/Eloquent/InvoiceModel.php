<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * @property string $id
 * @property string|null $number
 * @property string $patient_id
 * @property string $encounter_id
 * @property string $lifecycle
 * @property string $status
 * @property int $subtotal_amount
 * @property int $discount_amount
 * @property int $tax_basis_points
 * @property int $tax_amount
 * @property int $total_amount
 * @property int $paid_amount
 * @property string|null $discount_type
 * @property int|null $discount_value
 * @property string|null $discount_reason
 * @property Carbon|null $issued_at
 * @property Carbon|null $voided_at
 * @property string|null $void_reason
 * @property string|null $voided_by
 * @property Carbon $created_at
 */
final class InvoiceModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'invoices';

    /** @var list<string> */
    protected $fillable = ['branch_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['issued_at' => 'datetime', 'voided_at' => 'datetime'];
    }
}

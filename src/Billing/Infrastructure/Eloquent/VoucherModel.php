<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure\Eloquent;

use Illuminate\Support\Carbon;
use Src\Shared\Infrastructure\Eloquent\TenantMasterModel;

/**
 * P3-06: kode promo.
 *
 * @property string $code
 * @property string $name
 * @property string $kind
 * @property int $value
 * @property int $min_subtotal_amount
 * @property int|null $max_redemptions
 * @property int $max_per_patient
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_to
 */
final class VoucherModel extends TenantMasterModel
{
    protected $table = 'vouchers';

    /** @var list<string> */
    protected $fillable = ['code', 'name', 'kind', 'value', 'min_subtotal_amount', 'max_redemptions', 'max_per_patient', 'valid_from', 'valid_to', 'branch_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'value' => 'integer', 'min_subtotal_amount' => 'integer', 'max_redemptions' => 'integer', 'max_per_patient' => 'integer', 'valid_from' => 'date', 'valid_to' => 'date'];
    }
}

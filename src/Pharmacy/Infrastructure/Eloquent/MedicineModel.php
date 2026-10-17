<?php

declare(strict_types=1);

namespace Src\Pharmacy\Infrastructure\Eloquent;

use Src\Shared\Infrastructure\Eloquent\TenantMasterModel;

/**
 * @property string $name
 * @property string $unit
 * @property int $sell_price_amount
 * @property int $min_stock
 * @property string|null $drug_class
 */
final class MedicineModel extends TenantMasterModel
{
    protected $table = 'medicines';

    /** @var list<string> */
    protected $fillable = ['name', 'unit', 'sell_price_amount', 'min_stock', 'drug_class', 'branch_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sell_price_amount' => 'integer', 'min_stock' => 'integer'];
    }
}

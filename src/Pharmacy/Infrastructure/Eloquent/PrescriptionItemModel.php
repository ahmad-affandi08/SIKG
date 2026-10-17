<?php

declare(strict_types=1);

namespace Src\Pharmacy\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * @property string $id
 * @property string $prescription_id
 * @property string|null $medicine_id
 * @property string|null $free_text_name
 * @property string $medicine_name
 * @property string $dose
 * @property string $frequency
 * @property string $duration
 * @property string|null $instructions
 * @property int $quantity
 * @property bool $dispense_in_clinic
 * @property int $unit_price_amount
 */
final class PrescriptionItemModel extends Model
{
    use BelongsToClinic;
    use UsesUlid;

    protected $table = 'prescription_items';

    /** @var list<string> */
    protected $fillable = ['prescription_id', 'medicine_id', 'free_text_name', 'medicine_name', 'dose', 'frequency', 'duration', 'instructions', 'quantity', 'dispense_in_clinic', 'unit_price_amount', 'dispensed_at', 'created_by', 'branch_id'];

    protected static function branchScoped(): bool
    {
        return false;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['dispense_in_clinic' => 'boolean', 'dispensed_at' => 'datetime'];
    }
}

<?php

declare(strict_types=1);

namespace Src\Clinical\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * @property string $id
 * @property string $plan_id
 * @property int $sequence
 * @property string $procedure_id
 * @property string $procedure_code
 * @property string $procedure_name
 * @property int|null $tooth_fdi
 * @property int $quantity
 * @property int $unit_price_amount
 * @property int $estimate_amount
 * @property string $status
 * @property string|null $notes
 * @property string|null $encounter_id
 * @property Carbon|null $completed_at
 */
final class TreatmentPlanItemModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'treatment_plan_items';

    /** @var list<string> */
    protected $fillable = ['plan_id', 'sequence', 'procedure_id', 'procedure_code', 'procedure_name', 'tooth_fdi', 'quantity', 'unit_price_amount', 'estimate_amount', 'status', 'notes', 'encounter_id', 'completed_at', 'branch_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }
}

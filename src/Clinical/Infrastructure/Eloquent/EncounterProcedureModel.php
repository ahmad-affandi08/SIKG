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
 * @property string $encounter_id
 * @property string $procedure_id
 * @property string $procedure_code
 * @property string $procedure_name
 * @property string $scope
 * @property int|null $tooth_fdi
 * @property string|null $surfaces
 * @property string|null $jaw
 * @property int $quantity
 * @property int $unit_price_amount
 * @property string|null $notes
 * @property string $performed_by
 * @property Carbon $performed_at
 */
final class EncounterProcedureModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'encounter_procedures';

    /** @var list<string> */
    protected $fillable = ['encounter_id', 'procedure_id', 'procedure_code', 'procedure_name', 'scope', 'tooth_fdi', 'surfaces', 'jaw', 'quantity', 'unit_price_amount', 'notes', 'performed_by', 'performed_at', 'branch_id'];

    protected static function branchScoped(): bool
    {
        return false;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['performed_at' => 'datetime'];
    }
}

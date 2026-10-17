<?php

declare(strict_types=1);

namespace Src\Orthodontics\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * @property string $id
 * @property string $patient_id
 * @property string $doctor_id
 * @property string $appliance
 * @property string $title
 * @property string|null $plan_notes
 * @property int $total_fee_amount
 * @property Carbon $start_date
 * @property int $expected_months
 * @property string $status
 * @property Carbon|null $ended_at
 * @property string|null $ended_note
 * @property int $version
 */
final class OrthoCaseModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'orthodontic_cases';

    /** @var list<string> */
    protected $fillable = ['branch_id'];

    protected static function branchScoped(): bool
    {
        return false;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['total_fee_amount' => 'integer', 'start_date' => 'date', 'expected_months' => 'integer', 'ended_at' => 'datetime', 'version' => 'integer'];
    }
}

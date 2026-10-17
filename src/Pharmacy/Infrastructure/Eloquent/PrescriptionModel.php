<?php

declare(strict_types=1);

namespace Src\Pharmacy\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * @property string $id
 * @property string $encounter_id
 * @property string $patient_id
 * @property string $doctor_id
 * @property Carbon $issued_at
 * @property string $status
 * @property string|null $void_reason
 * @property string|null $allergy_acknowledged
 */
final class PrescriptionModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'prescriptions';

    /** @var list<string> */
    protected $fillable = ['encounter_id', 'patient_id', 'doctor_id', 'issued_at', 'status', 'void_reason', 'allergy_acknowledged', 'branch_id'];

    protected static function branchScoped(): bool
    {
        return false;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['issued_at' => 'datetime'];
    }
}

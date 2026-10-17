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
 * @property string $patient_id
 * @property string|null $appointment_id
 * @property string $doctor_id
 * @property string|null $chair_id
 * @property Carbon $started_at
 * @property Carbon|null $closed_at
 * @property string $status
 * @property string|null $chief_complaint
 * @property string|null $complaint_history
 * @property array<string, mixed>|null $vitals
 * @property Carbon|null $anamnesis_at
 * @property int $version
 */
final class EncounterModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'encounters';

    /** @var list<string> */
    protected $fillable = ['patient_id', 'appointment_id', 'doctor_id', 'chair_id', 'started_at', 'closed_at', 'closed_by', 'status', 'chief_complaint', 'complaint_history', 'vitals', 'anamnesis_at', 'version', 'branch_id'];

    protected static function branchScoped(): bool
    {
        return false;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'closed_at' => 'datetime', 'anamnesis_at' => 'datetime', 'vitals' => 'array'];
    }
}

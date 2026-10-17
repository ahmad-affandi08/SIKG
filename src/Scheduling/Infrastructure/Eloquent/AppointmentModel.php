<?php

declare(strict_types=1);

namespace Src\Scheduling\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * @property string $id
 * @property string $patient_id
 * @property string $doctor_id
 * @property string|null $chair_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string $status
 * @property string $source
 * @property int|null $queue_no
 * @property Carbon|null $queue_date
 * @property string|null $complaint
 * @property string|null $cancel_reason
 * @property string|null $reschedule_reason
 * @property Carbon|null $checked_in_at
 * @property int $version
 */
final class AppointmentModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'appointments';

    /** @var list<string> */
    protected $fillable = ['patient_id', 'doctor_id', 'chair_id', 'starts_at', 'ends_at', 'status', 'source', 'queue_no', 'queue_date', 'complaint', 'cancel_reason', 'reschedule_reason', 'checked_in_at', 'version', 'branch_id'];

    /** Janji berlaku untuk seluruh klinik; tidak dibatasi cabang pengguna. */
    protected static function branchScoped(): bool
    {
        return false;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'queue_date' => 'date', 'checked_in_at' => 'datetime'];
    }
}

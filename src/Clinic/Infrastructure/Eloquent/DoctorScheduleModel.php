<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * @property string $id
 * @property string $doctor_id
 * @property int $weekday
 * @property string $start_time
 * @property string $end_time
 */
final class DoctorScheduleModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'doctor_schedules';

    /** @var list<string> */
    protected $fillable = ['doctor_id', 'weekday', 'start_time', 'end_time', 'branch_id'];

    protected static function branchScoped(): bool
    {
        return false;
    }
}

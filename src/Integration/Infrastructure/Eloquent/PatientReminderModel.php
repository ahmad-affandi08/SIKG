<?php

declare(strict_types=1);

namespace Src\Integration\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * @property string $id
 * @property string $patient_id
 * @property string|null $appointment_id
 * @property string|null $encounter_id
 * @property string $kind
 * @property string $status
 * @property string|null $channel
 * @property Carbon $due_at
 * @property int $attempts
 * @property string|null $last_error
 * @property Carbon|null $sent_at
 */
final class PatientReminderModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'patient_reminders';

    /** @var list<string> */
    protected $fillable = ['patient_id', 'appointment_id', 'encounter_id', 'kind', 'status', 'channel', 'due_at', 'attempts', 'last_error', 'sent_at', 'sent_by', 'branch_id'];

    protected static function branchScoped(): bool
    {
        return false;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'sent_at' => 'datetime', 'attempts' => 'integer'];
    }
}

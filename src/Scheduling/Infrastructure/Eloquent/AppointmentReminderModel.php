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
 * @property string $appointment_id
 * @property string $kind
 * @property string $channel
 * @property string $status
 * @property int $attempts
 * @property Carbon|null $last_attempt_at
 */
final class AppointmentReminderModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'appointment_reminders';

    /** @var list<string> */
    protected $fillable = ['appointment_id', 'kind', 'channel', 'status', 'attempts', 'last_attempt_at', 'branch_id'];

    protected static function branchScoped(): bool
    {
        return false;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['attempts' => 'integer', 'last_attempt_at' => 'datetime'];
    }
}

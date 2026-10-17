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
 * @property string $encounter_id
 * @property string $patient_id
 * @property string $status
 * @property int $attempts
 * @property Carbon|null $next_attempt_at
 * @property string|null $last_error
 * @property string|null $encounter_ihs_id
 * @property Carbon|null $sent_at
 * @property Carbon $created_at
 */
final class SatuSehatSubmissionModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'satusehat_submissions';

    /** @var list<string> */
    protected $fillable = ['encounter_id', 'patient_id', 'status', 'attempts', 'next_attempt_at', 'last_error', 'encounter_ihs_id', 'sent_at', 'branch_id'];

    protected static function branchScoped(): bool
    {
        return false;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['attempts' => 'integer', 'next_attempt_at' => 'datetime', 'sent_at' => 'datetime'];
    }
}

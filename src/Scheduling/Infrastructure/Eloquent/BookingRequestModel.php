<?php

declare(strict_types=1);

namespace Src\Scheduling\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * P3-01: permintaan booking dari halaman publik. Nomor HP hanya terenkripsi; token status hanya berupa hash.
 *
 * @property string $id
 * @property string $token_hash
 * @property string $doctor_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string $patient_name
 * @property string $phone_encrypted
 * @property string $phone_hash
 * @property string|null $complaint
 * @property string $status
 * @property bool $verified
 * @property string|null $code_hash
 * @property Carbon|null $code_expires_at
 * @property int $code_attempts
 * @property int $code_sent_count
 * @property string|null $patient_id
 * @property string|null $appointment_id
 * @property string|null $decision_note
 * @property Carbon $expires_at
 * @property Carbon|null $decided_at
 * @property Carbon|null $created_at
 */
final class BookingRequestModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'booking_requests';

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
        return [
            'starts_at' => 'datetime', 'ends_at' => 'datetime', 'verified' => 'boolean', 'code_expires_at' => 'datetime', 'code_attempts' => 'integer',
            'code_sent_count' => 'integer', 'expires_at' => 'datetime', 'decided_at' => 'datetime',
        ];
    }
}

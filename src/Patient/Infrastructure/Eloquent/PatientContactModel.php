<?php

declare(strict_types=1);

namespace Src\Patient\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * @property string $id
 * @property string $clinic_id
 * @property string $patient_id
 * @property string|null $email_encrypted
 * @property string|null $telegram_chat_id_encrypted
 * @property bool $reminders_opt_in
 * @property Carbon|null $opt_in_at
 * @property string|null $telegram_link_hash
 * @property Carbon|null $telegram_link_expires_at
 */
final class PatientContactModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'patient_contacts';

    /** @var list<string> */
    protected $fillable = ['patient_id', 'branch_id'];

    /** Data pasien berlaku di seluruh cabang klinik; tidak dibatasi cabang pengguna. */
    protected static function branchScoped(): bool
    {
        return false;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['reminders_opt_in' => 'boolean', 'opt_in_at' => 'datetime', 'telegram_link_expires_at' => 'datetime'];
    }
}

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
 * @property string $medical_record_no
 * @property string $name
 * @property string|null $nik_encrypted
 * @property string|null $nik_hash
 * @property string|null $nik_missing_reason
 * @property Carbon $birth_date
 * @property string $sex
 * @property string|null $address
 * @property string|null $phone_encrypted
 * @property string|null $phone_hash
 * @property string|null $occupation
 * @property string|null $emergency_contact
 * @property Carbon|null $data_consent_at
 * @property string|null $merged_into_id
 */
final class PatientModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'patients';

    /** @var list<string> */
    protected $fillable = ['name', 'name_search', 'nik_encrypted', 'nik_hash', 'nik_missing_reason', 'birth_date', 'sex', 'address', 'phone_encrypted', 'phone_hash', 'occupation', 'emergency_contact', 'data_consent_at', 'medical_record_no', 'branch_id', 'merged_into_id', 'merged_at'];

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
        return ['birth_date' => 'date', 'data_consent_at' => 'datetime'];
    }
}

<?php

declare(strict_types=1);

namespace Src\Patient\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * @property string $id
 * @property string $patient_id
 * @property string $type
 * @property string $name
 * @property string|null $severity
 * @property string|null $notes
 */
final class PatientMedicalHistoryModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'patient_medical_histories';

    /** @var list<string> */
    protected $fillable = ['patient_id', 'type', 'name', 'severity', 'notes', 'branch_id'];

    /** Data pasien berlaku di seluruh cabang klinik; tidak dibatasi cabang pengguna. */
    protected static function branchScoped(): bool
    {
        return false;
    }
}

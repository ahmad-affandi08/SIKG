<?php

declare(strict_types=1);

namespace Src\Clinical\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * @property string $id
 * @property string $encounter_id
 * @property string $diagnosis_id
 * @property string $icd10_code
 * @property string $diagnosis_name
 * @property int|null $tooth_fdi
 * @property string|null $notes
 */
final class EncounterDiagnosisModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'encounter_diagnoses';

    /** @var list<string> */
    protected $fillable = ['encounter_id', 'diagnosis_id', 'icd10_code', 'diagnosis_name', 'tooth_fdi', 'notes', 'branch_id'];

    protected static function branchScoped(): bool
    {
        return false;
    }
}

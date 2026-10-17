<?php

declare(strict_types=1);

namespace Src\Patient\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * Persetujuan data pasien: hanya ditambah, tidak diubah atau dihapus (bukti persetujuan).
 */
/**
 * @property string $text_version
 * @property Carbon $given_at
 * @property string|null $recorded_by
 */
final class PatientConsentModel extends Model
{
    use BelongsToClinic;
    use UsesUlid;

    public const UPDATED_AT = null;

    protected $table = 'patient_consents';

    /** @var list<string> */
    protected $fillable = ['patient_id', 'text_version', 'given_at', 'recorded_by'];

    protected static function branchScoped(): bool
    {
        return false;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['given_at' => 'datetime'];
    }
}

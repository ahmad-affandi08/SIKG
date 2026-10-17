<?php

declare(strict_types=1);

namespace Src\Clinical\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * Dokumen klinis yang sudah diterbitkan bersifat final: tidak diubah dan tidak dihapus.
 *
 * @property string $id
 * @property string $patient_id
 * @property string|null $encounter_id
 * @property string $type
 * @property string $number
 * @property string $title
 * @property string $body
 * @property string $doctor_id
 * @property string $doctor_name
 * @property string|null $doctor_sip
 * @property string $issued_by
 * @property Carbon $issued_at
 */
final class ClinicalDocumentModel extends Model
{
    use BelongsToClinic;
    use UsesUlid;

    public const UPDATED_AT = null;

    protected $table = 'clinical_documents';

    /** @var list<string> */
    protected $fillable = ['branch_id'];

    protected static function booted(): void
    {
        self::updating(function (): void {
            throw new LogicException('Dokumen yang sudah diterbitkan tidak dapat diubah; terbitkan dokumen baru.');
        });
        self::deleting(function (): void {
            throw new LogicException('Dokumen yang sudah diterbitkan tidak dapat dihapus.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['issued_at' => 'datetime'];
    }
}

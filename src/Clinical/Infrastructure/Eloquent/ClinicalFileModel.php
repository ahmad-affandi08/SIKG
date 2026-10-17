<?php

declare(strict_types=1);

namespace Src\Clinical\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * @property string $id
 * @property string $patient_id
 * @property string $encounter_id
 * @property int|null $tooth_fdi
 * @property string $path
 * @property string $mime
 * @property int $size
 * @property string $kind
 * @property bool $encrypted
 * @property string|null $caption
 * @property string $uploaded_by
 * @property Carbon $created_at
 */
final class ClinicalFileModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'clinical_files';

    /** @var list<string> */
    protected $fillable = ['patient_id', 'encounter_id', 'tooth_fdi', 'disk', 'path', 'mime', 'size', 'kind', 'encrypted', 'caption', 'uploaded_by', 'branch_id'];

    protected static function branchScoped(): bool
    {
        return false;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['encrypted' => 'boolean'];
    }
}

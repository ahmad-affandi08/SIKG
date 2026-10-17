<?php

declare(strict_types=1);

namespace Src\Integration\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * @property string $id
 * @property string $doctor_id
 * @property string|null $nik_encrypted
 * @property string|null $ihs_id
 */
final class SatuSehatPractitionerModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'satusehat_practitioners';

    /** @var list<string> */
    protected $hidden = ['nik_encrypted'];

    /** @var list<string> */
    protected $fillable = ['branch_id'];

    protected static function branchScoped(): bool
    {
        return false;
    }
}

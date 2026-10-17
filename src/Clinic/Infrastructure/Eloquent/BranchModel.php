<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * @property string $id
 * @property string $clinic_id
 * @property string $name
 */
final class BranchModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'branches';

    protected $fillable = ['name', 'address', 'is_active'];

    /**
     * Cabang tidak punya branch_id; dibatasi per klinik saja.
     */
    protected static function branchScoped(): bool
    {
        return false;
    }
}

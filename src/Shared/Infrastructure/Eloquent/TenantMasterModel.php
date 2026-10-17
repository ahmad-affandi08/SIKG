<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * Dasar model master data per klinik: ULID, soft delete, scope klinik. `branch_id` kosong berarti berlaku untuk semua
 * cabang, jadi master data tidak dibatasi oleh cabang pengguna.
 *
 * @property string $id
 * @property string $clinic_id
 * @property string|null $branch_id
 * @property bool $is_active
 */
abstract class TenantMasterModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    /** @var array<string, string> */
    protected $casts = ['is_active' => 'boolean'];

    protected static function branchScoped(): bool
    {
        return false;
    }
}

<?php

declare(strict_types=1);

namespace Src\Pharmacy\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * P2-05: bahan habis pakai standar per tindakan (dinonaktifkan, tidak dihapus).
 *
 * @property string $id
 * @property string $procedure_id
 * @property string $medicine_id
 * @property int $quantity
 * @property bool $is_active
 */
final class ProcedureMaterialModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'procedure_materials';

    /** @var list<string> */
    protected $fillable = ['branch_id'];

    protected static function branchScoped(): bool
    {
        return false;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'integer', 'is_active' => 'boolean'];
    }
}

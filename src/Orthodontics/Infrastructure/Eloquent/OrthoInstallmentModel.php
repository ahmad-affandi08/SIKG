<?php

declare(strict_types=1);

namespace Src\Orthodontics\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * @property string $id
 * @property string $case_id
 * @property int $seq
 * @property Carbon $due_date
 * @property int $amount
 * @property string $status
 * @property string|null $encounter_id
 * @property string|null $invoice_id
 * @property Carbon|null $invoiced_at
 */
final class OrthoInstallmentModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'orthodontic_installments';

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
        return ['seq' => 'integer', 'amount' => 'integer', 'due_date' => 'date', 'invoiced_at' => 'datetime'];
    }
}

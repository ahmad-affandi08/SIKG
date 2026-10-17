<?php

declare(strict_types=1);

namespace Src\Insurance\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * @property string $id
 * @property string $claim_id
 * @property int $amount
 * @property string $method_id
 * @property string|null $reference_no
 * @property string $idempotency_key
 * @property Carbon $received_at
 * @property string $received_by
 */
final class ClaimReceiptModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'insurance_claim_receipts';

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
        return ['amount' => 'integer', 'received_at' => 'datetime'];
    }
}

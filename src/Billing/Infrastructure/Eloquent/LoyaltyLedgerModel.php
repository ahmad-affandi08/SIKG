<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * P3-06: buku poin loyalitas hanya bertambah; koreksi berupa baris baru.
 *
 * @property string $id
 * @property string $patient_id
 * @property int $delta
 * @property int $balance_after
 * @property string $kind
 * @property string|null $invoice_id
 * @property string|null $dedupe_key
 * @property string|null $note
 * @property Carbon $created_at
 */
final class LoyaltyLedgerModel extends Model
{
    use BelongsToClinic;
    use UsesUlid;

    public const UPDATED_AT = null;

    protected $table = 'loyalty_ledger';

    /** @var list<string> */
    protected $fillable = ['branch_id'];

    protected static function branchScoped(): bool
    {
        return false;
    }

    protected static function booted(): void
    {
        self::updating(function (): void {
            throw new LogicException('Buku poin tidak dapat diubah; buat baris koreksi.');
        });
        self::deleting(function (): void {
            throw new LogicException('Buku poin tidak dapat dihapus.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}

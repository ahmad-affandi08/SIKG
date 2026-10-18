<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * P3-06: saldo poin pasien; hanya berubah bersama baris buku poin.
 *
 * @property string $id
 * @property string $patient_id
 * @property int $points
 */
final class LoyaltyBalanceModel extends Model
{
    use BelongsToClinic;
    use UsesUlid;

    protected $table = 'loyalty_balances';

    /** @var list<string> */
    protected $fillable = ['branch_id'];

    protected static function branchScoped(): bool
    {
        return false;
    }
}

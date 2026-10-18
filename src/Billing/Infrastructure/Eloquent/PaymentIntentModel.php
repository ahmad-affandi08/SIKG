<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * P3-05: niat bayar online untuk satu tagihan.
 *
 * @property string $id
 * @property string|null $branch_id
 * @property string $invoice_id
 * @property string $order_id
 * @property string $provider
 * @property int $amount
 * @property string $status
 * @property int|null $active_slot
 * @property string|null $payment_url
 * @property string|null $gateway_ref
 * @property Carbon $expires_at
 * @property Carbon|null $paid_at
 * @property string|null $payment_id
 * @property string|null $note
 * @property string|null $created_by
 * @property Carbon|null $created_at
 */
final class PaymentIntentModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'payment_intents';

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
        return ['amount' => 'integer', 'active_slot' => 'integer', 'expires_at' => 'datetime', 'paid_at' => 'datetime', 'last_checked_at' => 'datetime'];
    }
}

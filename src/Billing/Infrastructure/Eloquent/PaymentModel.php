<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * Pembayaran hanya bertambah: tidak diubah (selain penautan tutup kas sekali) dan tidak dihapus (M5, KSR-07).
 *
 * @property string $id
 * @property string $invoice_id
 * @property string $payment_method_id
 * @property string $type
 * @property int $amount
 * @property Carbon $paid_at
 * @property string $received_by
 * @property string|null $reference_no
 * @property string $idempotency_key
 * @property string|null $receipt_no
 * @property string|null $reason
 * @property string|null $cash_closing_id
 */
final class PaymentModel extends Model
{
    use BelongsToClinic;
    use UsesUlid;

    public const UPDATED_AT = null;

    protected $table = 'payments';

    /** @var list<string> */
    protected $fillable = ['branch_id'];

    protected static function booted(): void
    {
        self::updating(function (self $model): void {
            $changed = array_keys($model->getDirty());
            if ($changed !== ['cash_closing_id'] || $model->getOriginal('cash_closing_id') !== null) {
                throw new LogicException('Pembayaran tidak dapat diubah; koreksi lewat transaksi pembalik.');
            }
        });
        self::deleting(function (): void {
            throw new LogicException('Pembayaran tidak dapat dihapus; koreksi lewat transaksi pembalik.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
    }
}

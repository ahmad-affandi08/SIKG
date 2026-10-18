<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * P3-05: pengaturan pembayaran online. Secret key hanya terenkripsi.
 *
 * @property string $clinic_id
 * @property bool $enabled
 * @property string $provider
 * @property string $environment
 * @property string|null $client_id
 * @property string|null $secret_key_encrypted
 * @property string|null $payment_method_id
 * @property int $expiry_minutes
 * @property string $webhook_token
 */
final class OnlinePaymentSettingModel extends Model
{
    use BelongsToClinic;
    use SoftDeletes;
    use UsesUlid;

    protected $table = 'online_payment_settings';

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
        return ['enabled' => 'boolean', 'expiry_minutes' => 'integer'];
    }
}

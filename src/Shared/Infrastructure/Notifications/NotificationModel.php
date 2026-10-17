<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Notifications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\ClinicScope;

/**
 * @property string $id
 * @property string $clinic_id
 * @property string $kind
 * @property string $title
 * @property string $body
 * @property string|null $link
 * @property string $permission
 * @property string $dedupe_key
 * @property Carbon $created_at
 */
final class NotificationModel extends Model
{
    use UsesUlid;

    public const UPDATED_AT = null;

    protected $table = 'notifications';

    /** @var list<string> */
    protected $fillable = ['clinic_id', 'branch_id', 'kind', 'title', 'body', 'link', 'permission', 'dedupe_key', 'created_at'];

    protected static function booted(): void
    {
        self::addGlobalScope(new ClinicScope(branchScoped: false));

        // Pemberitahuan hanya bertambah lewat aplikasi; pemangkasan 90 hari dilakukan perintah terjadwal (SYS-05).
        self::updating(static function (): never {
            throw new LogicException('Pemberitahuan tidak dapat diubah.');
        });
        self::deleting(static function (): never {
            throw new LogicException('Pemberitahuan tidak dapat dihapus lewat aplikasi.');
        });
    }
}

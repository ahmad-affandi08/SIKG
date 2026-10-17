<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Notifications;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Penanda "sudah dibaca" per pengguna (SYS-05). Hanya bertambah; dibuang bersama pemberitahuannya oleh perintah pangkas.
 *
 * @property string $notification_id
 * @property string $user_id
 */
final class NotificationReadModel extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'notification_reads';

    /** @var list<string> */
    protected $fillable = ['notification_id', 'user_id', 'read_at'];

    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new LogicException('Penanda dibaca tidak dapat diubah.');
        });
        self::deleting(static function (): never {
            throw new LogicException('Penanda dibaca tidak dapat dihapus lewat aplikasi.');
        });
    }
}

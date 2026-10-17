<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * SYS-03, M5: data klinis dan keuangan tidak pernah dihapus permanen oleh aplikasi. Model dengan SoftDeletes hanya boleh
 * dihapus lunak (kolom deleted_at); penghapusan permanen ditolak. Model tanpa SoftDeletes bersifat hanya-tambah (mis. pembayaran,
 * mutasi stok, catatan SOAP berversi) dan menolak penghapusan apa pun; koreksinya lewat void, refund, mutasi pembalik, atau addendum.
 * Penghapusan massal lewat query tidak melewati event model, jadi dijaga oleh guard dan tes SoftDeleteTest.
 */
trait RefusesHardDelete
{
    public static function bootRefusesHardDelete(): void
    {
        // registerModelEvent dipakai langsung karena `forceDeleting()` hanya ada pada model ber-SoftDeletes.
        static::registerModelEvent('forceDeleting', static function (): never {
            throw new LogicException('Data klinis dan keuangan tidak dapat dihapus permanen; pakai arsip, void, atau addendum (M5).');
        });

        if (! in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::deleting(static function (): never {
                throw new LogicException('Data ini hanya bertambah dan tidak dapat dihapus; koreksi lewat transaksi pembalik atau addendum (M5).');
            });
        }
    }
}

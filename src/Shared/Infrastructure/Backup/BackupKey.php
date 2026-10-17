<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Backup;

use Src\Shared\Application\Backup\BackupFailed;

/**
 * Kunci enkripsi cadangan (32 byte). Memakai BACKUP_KEY bila diatur (disarankan, disimpan terpisah dari server);
 * bila kosong diturunkan dari APP_KEY lewat HKDF. Kunci yang hilang membuat cadangan tidak dapat dibuka.
 */
final class BackupKey
{
    public static function resolve(?string $configured, ?string $appKey): string
    {
        if ($configured !== null && $configured !== '') {
            $raw = str_starts_with($configured, 'base64:') ? base64_decode(substr($configured, 7), true) : $configured;
            if ($raw === false || strlen($raw) < 16) {
                throw new BackupFailed('BACKUP_KEY tidak valid (minimal 16 byte, atau base64: untuk kunci biner).');
            }

            return hash_hkdf('sha256', $raw, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES, 'sikg-backup-v1');
        }
        if ($appKey === null || $appKey === '') {
            throw new BackupFailed('Kunci cadangan tidak tersedia: atur BACKUP_KEY atau APP_KEY.');
        }
        $raw = str_starts_with($appKey, 'base64:') ? base64_decode(substr($appKey, 7), true) : $appKey;
        if ($raw === false || $raw === '') {
            throw new BackupFailed('APP_KEY tidak valid untuk menurunkan kunci cadangan.');
        }

        return hash_hkdf('sha256', $raw, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES, 'sikg-backup-v1');
    }
}

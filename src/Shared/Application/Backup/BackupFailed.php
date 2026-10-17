<?php

declare(strict_types=1);

namespace Src\Shared\Application\Backup;

use RuntimeException;

/**
 * Kegagalan cadangan atau pemulihan dengan pesan yang aman dicatat dan dikirim lewat surel: tanpa nilai data, hanya nama
 * tabel dan angka. Galat lain (mis. galat SQL yang memuat nilai) dicatat hanya sebagai jenisnya.
 */
final class BackupFailed extends RuntimeException {}

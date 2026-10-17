<?php

declare(strict_types=1);

namespace Src\Shared\Application;

/**
 * Menulis audit_logs dalam transaksi yang sama dengan perubahannya. Hanya INSERT; tidak ada ubah atau hapus (M10).
 */
interface AuditLogger
{
    public function record(AuditEntry $entry): void;
}

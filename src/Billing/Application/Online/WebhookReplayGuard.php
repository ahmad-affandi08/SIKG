<?php

declare(strict_types=1);

namespace Src\Billing\Application\Online;

/**
 * Mencatat Request-Id notifikasi yang sudah diproses sehingga pengiriman ulang persis sama tidak memicu pekerjaan dan panggilan
 * keluar lagi. Hanya pelindung tambahan: pencatatan pembayaran tetap idempoten sendiri.
 */
interface WebhookReplayGuard
{
    /** @return bool true bila Request-Id ini belum pernah dilihat (dan kini dicatat) */
    public function firstSeen(string $clinicId, string $requestId): bool;

    /** Melepas catatan agar penyedia boleh mencoba lagi (dipakai bila pemrosesan gagal). */
    public function forget(string $clinicId, string $requestId): void;
}

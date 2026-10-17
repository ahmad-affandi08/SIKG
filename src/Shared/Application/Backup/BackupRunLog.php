<?php

declare(strict_types=1);

namespace Src\Shared\Application\Backup;

use DateTimeImmutable;

/**
 * Catatan jalannya cadangan, uji pemulihan, dan pemangkasan (SYS-02). Hanya bertambah; tanpa PHI.
 */
interface BackupRunLog
{
    public const BACKUP = 'backup';

    public const VERIFY = 'verify';

    public const PRUNE = 'prune';

    public const MIRROR = 'mirror';

    /**
     * @param  self::BACKUP|self::VERIFY|self::PRUNE|self::MIRROR  $kind
     * @param  array<string, mixed>  $details
     */
    public function record(string $kind, bool $ok, ?string $file, string $message, array $details, DateTimeImmutable $startedAt, DateTimeImmutable $finishedAt): void;

    /**
     * Catatan terakhir suatu jenis, atau null.
     *
     * @return array{ok: bool, file: string|null, message: string, details: array<string, mixed>, finished_at: DateTimeImmutable}|null
     */
    public function latest(string $kind, bool $onlyOk = false): ?array;

    /**
     * @return list<array<string, mixed>> terbaru lebih dulu
     */
    public function recent(int $limit): array;
}

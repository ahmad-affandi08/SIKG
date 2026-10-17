<?php

declare(strict_types=1);

namespace Src\Shared\Application\Backup;

use DateTimeImmutable;
use ReflectionClass;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Domain\Backup\BackupRetention;
use Throwable;

/**
 * SYS-02: cadangan basis data dan berkas unggahan otomatis, uji pemulihan terjadwal, dan pemangkasan menurut retensi.
 * Dijalankan scheduler (satu cron `schedule:run`): cadangan hanya berjalan bila sudah jatuh tempo menurut interval, jadi cron
 * yang terlewat atau terlambat tidak membuat cadangan hilang (docs/rules/hosting.md butir 5). Setiap jalan tercatat tanpa PHI.
 */
final readonly class BackupService
{
    /** Uji pemulihan dianggap basi setelah sekian hari tanpa uji yang berhasil. */
    public const VERIFY_STALE_DAYS = 8;

    public function __construct(
        private BackupEngine $engine,
        private BackupRunLog $log,
        private Clock $clock,
        private AuditLogger $audit,
        private int $intervalHours,
    ) {}

    /**
     * @return array{status: 'ok'|'skipped'|'failed', file: string|null, message: string, files: array<string, int>|null}
     */
    public function backup(bool $force = false): array
    {
        $now = $this->clock->now();
        $last = $this->log->latest(BackupRunLog::BACKUP, true);
        if (! $force && $last !== null && $now->getTimestamp() - $last['finished_at']->getTimestamp() < $this->intervalHours * 3600) {
            return ['status' => 'skipped', 'file' => null, 'message' => 'Belum jatuh tempo.', 'files' => null];
        }

        $mirror = null;
        try {
            $artifact = $this->engine->backup($now);
            $this->log->record(BackupRunLog::BACKUP, true, $artifact['name'], 'Cadangan data dibuat.', [
                'size' => $artifact['size'], 'tables' => $artifact['tables'], 'rows' => $artifact['rows'], 'sha256' => $artifact['checksum'],
            ], $now, $this->clock->now());
        } catch (Throwable $e) {
            $this->fail(BackupRunLog::BACKUP, null, $e, $now);

            return ['status' => 'failed', 'file' => null, 'message' => self::safe($e), 'files' => null];
        }

        try {
            $mirror = $this->engine->mirrorFiles();
            $ok = $mirror['missing'] === 0;
            $this->log->record(BackupRunLog::MIRROR, $ok, null, $ok ? 'Berkas unggahan disalin.' : 'Sebagian berkas unggahan tidak dapat disalin.', $mirror, $now, $this->clock->now());
        } catch (Throwable $e) {
            $this->fail(BackupRunLog::MIRROR, null, $e, $now);

            return ['status' => 'failed', 'file' => $artifact['name'], 'message' => 'Cadangan data dibuat, tetapi salinan berkas gagal: '.self::safe($e), 'files' => null];
        }

        return ['status' => $mirror['missing'] === 0 ? 'ok' : 'failed', 'file' => $artifact['name'], 'message' => 'Cadangan selesai.', 'files' => $mirror];
    }

    /**
     * @return array{status: 'ok'|'failed', file: string|null, message: string}
     */
    public function verify(?string $name = null): array
    {
        $started = $this->clock->now();
        try {
            $name ??= $this->newest() ?? throw new BackupFailed('Belum ada cadangan untuk diuji.');
            $result = $this->engine->verify($name);
            $this->log->record(BackupRunLog::VERIFY, true, $name, 'Uji pemulihan berhasil.', ['tables' => $result['tables'], 'rows' => $result['rows'], 'sha256' => $result['checksum']], $started, $this->clock->now());

            return ['status' => 'ok', 'file' => $name, 'message' => "Uji pemulihan berhasil: {$result['tables']} tabel, {$result['rows']} baris."];
        } catch (Throwable $e) {
            $this->fail(BackupRunLog::VERIFY, $name, $e, $started);

            return ['status' => 'failed', 'file' => $name, 'message' => self::safe($e)];
        }
    }

    /**
     * @return array{status: 'ok'|'failed', removed: list<string>, message: string}
     */
    public function prune(): array
    {
        $started = $this->clock->now();
        try {
            $expired = BackupRetention::expired($this->engine->backups(), $started);
            foreach ($expired as $name) {
                $this->engine->remove($name);
            }
            $this->log->record(BackupRunLog::PRUNE, true, null, count($expired).' cadangan lama dibuang sesuai retensi.', ['removed' => count($expired)], $started, $this->clock->now());

            return ['status' => 'ok', 'removed' => $expired, 'message' => count($expired).' cadangan dibuang.'];
        } catch (Throwable $e) {
            $this->fail(BackupRunLog::PRUNE, null, $e, $started);

            return ['status' => 'failed', 'removed' => [], 'message' => self::safe($e)];
        }
    }

    /**
     * Pemulihan bencana ke basis data kosong yang skemanya sudah dimigrasi. Dicatat di audit (tanpa pelaku: dijalankan operator
     * server lewat konsol).
     *
     * @return array{name: string, tables: int, rows: int}
     */
    public function restore(string $name): array
    {
        $result = $this->engine->restore($name);
        $this->audit->record(new AuditEntry(null, 'backup.restored', 'backup', 'system', null, ['file' => $name, 'tables' => $result['tables'], 'rows' => $result['rows']]));

        return $result;
    }

    /**
     * Keadaan cadangan untuk halaman Sistem dan peringatan: ok, warn, atau fail beserta alasannya.
     *
     * @return array{state: 'ok'|'warn'|'fail', reasons: list<string>, interval_hours: int, last_backup: array<string, mixed>|null, last_verify: array<string, mixed>|null, last_failure: array<string, mixed>|null, backups: int, recent: list<array<string, mixed>>}
     */
    public function status(): array
    {
        $now = $this->clock->now();
        $backup = $this->log->latest(BackupRunLog::BACKUP, true);
        $verify = $this->log->latest(BackupRunLog::VERIFY, true);
        $latestBackup = $this->log->latest(BackupRunLog::BACKUP);
        $latestVerify = $this->log->latest(BackupRunLog::VERIFY);
        $latestMirror = $this->log->latest(BackupRunLog::MIRROR);

        $state = 'ok';
        $reasons = [];
        if ($backup === null) {
            $state = 'warn';
            $reasons[] = 'Belum pernah ada cadangan yang berhasil.';
        } elseif ($now->getTimestamp() - $backup['finished_at']->getTimestamp() > 2 * $this->intervalHours * 3600) {
            $state = 'fail';
            $reasons[] = 'Cadangan terakhir yang berhasil lebih tua dari dua kali interval ('.$this->intervalHours.' jam).';
        }
        if ($latestBackup !== null && ! $latestBackup['ok'] && ($backup === null || $latestBackup['finished_at'] > $backup['finished_at'])) {
            $state = 'fail';
            $reasons[] = 'Cadangan terakhir gagal: '.$latestBackup['message'];
        }
        if ($latestMirror !== null && ! $latestMirror['ok']) {
            $state = 'fail';
            $reasons[] = 'Salinan berkas unggahan terakhir gagal: '.$latestMirror['message'];
        }
        if ($latestVerify !== null && ! $latestVerify['ok'] && ($verify === null || $latestVerify['finished_at'] > $verify['finished_at'])) {
            $state = 'fail';
            $reasons[] = 'Uji pemulihan terakhir gagal: '.$latestVerify['message'];
        } elseif ($backup !== null && ($verify === null || $now->getTimestamp() - $verify['finished_at']->getTimestamp() > self::VERIFY_STALE_DAYS * 86400)) {
            $state = $state === 'ok' ? 'warn' : $state;
            $reasons[] = 'Uji pemulihan belum berhasil dalam '.self::VERIFY_STALE_DAYS.' hari terakhir.';
        }

        $failure = $latestBackup !== null && ! $latestBackup['ok'] ? $latestBackup : null;
        try {
            $stored = count($this->engine->backups());
        } catch (Throwable) {
            // Disk cadangan tidak terbaca: keadaan tetap dilaporkan, jumlah berkas dianggap nol.
            $state = 'fail';
            $reasons[] = 'Disk cadangan tidak dapat dibaca.';
            $stored = 0;
        }

        return [
            'state' => $state,
            'reasons' => $reasons,
            'interval_hours' => $this->intervalHours,
            'last_backup' => $backup === null ? null : self::present($backup),
            'last_verify' => $verify === null ? null : self::present($verify),
            'last_failure' => $failure === null ? null : self::present($failure),
            'backups' => $stored,
            'recent' => $this->log->recent(10),
        ];
    }

    private function newest(): ?string
    {
        $backups = $this->engine->backups();

        return $backups === [] ? null : array_key_last($backups);
    }

    /**
     * @param  BackupRunLog::BACKUP|BackupRunLog::VERIFY|BackupRunLog::PRUNE|BackupRunLog::MIRROR  $kind
     */
    private function fail(string $kind, ?string $file, Throwable $e, DateTimeImmutable $started): void
    {
        $this->log->record($kind, false, $file, self::safe($e), [], $started, $this->clock->now());
        report($e);
    }

    private static function safe(Throwable $e): string
    {
        return $e instanceof BackupFailed ? $e->getMessage() : 'Galat tak terduga ('.(new ReflectionClass($e))->getShortName().'); lihat log aplikasi.';
    }

    /**
     * @param  array{ok: bool, file: string|null, message: string, details: array<string, mixed>, finished_at: DateTimeImmutable}  $entry
     * @return array<string, mixed>
     */
    private static function present(array $entry): array
    {
        return ['file' => $entry['file'], 'message' => $entry['message'], 'details' => $entry['details'], 'finished_at' => $entry['finished_at']->format('c')];
    }
}

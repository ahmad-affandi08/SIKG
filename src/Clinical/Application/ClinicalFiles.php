<?php

declare(strict_types=1);

namespace Src\Clinical\Application;

use Src\Clinical\Domain\ClinicalFileRecord;
use Src\Clinical\Domain\ClinicalFileRepository;
use Src\Clinical\Domain\EncounterNotFound;
use Src\Clinical\Domain\FileKind;
use Src\Clinical\Domain\InvalidClinicalData;
use Src\Clinical\Domain\Tooth;
use Src\Shared\Application\AccessLogger;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Transaction;

/**
 * RM-08: foto klinis, rontgen, dan dokumen (JPG, PNG, PDF, maksimal 10 MB) pada kunjungan atau gigi. Hanya kunjungan yang
 * masih berjalan menerima atau melepas berkas; membuka berkas tercatat di access log (M10).
 */
final readonly class ClinicalFiles
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    /** @var array<string, string> mime => ekstensi */
    public const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];

    public function __construct(
        private ClinicalFileRepository $files,
        private FileVault $vault,
        private EncounterGuard $guard,
        private AccessLogger $access,
        private Transaction $tx,
        private AuditLogger $audit,
        private Clock $clock,
    ) {}

    public function upload(string $actorId, string $encounterId, string $contents, string $mime, string $kind, ?int $toothFdi, ?string $caption): string
    {
        $extension = self::TYPES[$mime] ?? throw new InvalidClinicalData('clinical.file_type', 'Berkas harus berformat JPG, PNG, atau PDF.');
        $size = strlen($contents);
        if ($size === 0 || $size > self::MAX_BYTES) {
            throw new InvalidClinicalData('clinical.file_size', 'Ukuran berkas harus lebih dari 0 dan maksimal 10 MB.');
        }
        $fileKind = FileKind::tryFrom($kind) ?? throw new InvalidClinicalData('clinical.file_kind', 'Jenis berkas tidak valid.');
        $tooth = $toothFdi === null ? null : (new Tooth($toothFdi))->fdi;
        $caption = $caption === null || trim($caption) === '' ? null : mb_substr(trim($caption), 0, 255);

        return $this->tx->run(function () use ($actorId, $encounterId, $contents, $mime, $extension, $size, $fileKind, $tooth, $caption): string {
            $encounter = $this->guard->load($encounterId);
            $this->guard->assertOpen($encounter);

            $stored = $this->vault->put($encounter->patientId, $contents, $extension);
            $id = $this->files->add($actorId, $encounter->patientId, $encounterId, $tooth, $stored['path'], $mime, $size, $fileKind, $stored['encrypted'], $caption, $this->clock->now());
            $this->audit->record(new AuditEntry($actorId, 'encounter.file_uploaded', 'encounter', $encounterId, null, ['file_id' => $id, 'kind' => $fileKind->value, 'mime' => $mime, 'size' => $size, 'tooth_fdi' => $tooth]));

            return $id;
        });
    }

    public function remove(string $actorId, string $encounterId, string $fileId): void
    {
        $this->tx->run(function () use ($actorId, $encounterId, $fileId): void {
            $encounter = $this->guard->load($encounterId);
            $this->guard->assertOpen($encounter);
            $file = $this->files->find($fileId);
            if ($file === null || $file->encounterId !== $encounterId) {
                throw new InvalidClinicalData('clinical.entry_not_found', 'Berkas tidak ditemukan pada kunjungan ini.');
            }

            $this->files->remove($actorId, $fileId);
            $this->audit->record(new AuditEntry($actorId, 'encounter.file_removed', 'encounter', $encounterId, ['file_id' => $fileId, 'kind' => $file->kind->value], null));
        });
    }

    /**
     * @return array{file: ClinicalFileRecord, contents: string}
     */
    public function open(string $fileId, string $route): array
    {
        $file = $this->files->find($fileId) ?? throw new EncounterNotFound;
        $this->access->patientRecordOpened($file->patientId, $route);

        return ['file' => $file, 'contents' => $this->vault->get($file->path, $file->encrypted)];
    }
}

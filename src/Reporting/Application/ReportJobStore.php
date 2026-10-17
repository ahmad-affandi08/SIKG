<?php

declare(strict_types=1);

namespace Src\Reporting\Application;

use DateTimeImmutable;
use Src\Reporting\Domain\ExportFormat;
use Src\Reporting\Domain\ReportKey;
use Src\Reporting\Domain\ReportScope;

/**
 * Catatan berkas laporan yang dibuat lewat antrean (`report_jobs`). Satu baris per permintaan ekspor; tidak dihapus.
 *
 * @phpstan-type JobRow array{id: string, report_key: string, format: string, status: string, scope: string, filters: array<string, mixed>, requested_by: string, file_size: int|null, has_file: bool, error: string|null, created_at: string, started_at: string|null, finished_at: string|null, expires_at: string|null}
 */
interface ReportJobStore
{
    /**
     * @param  array<string, mixed>  $filters
     * @return JobRow
     */
    public function create(ReportKey $key, ExportFormat $format, ReportScope $scope, array $filters, string $userId, DateTimeImmutable $at): array;

    /**
     * @return JobRow|null
     */
    public function find(string $id): ?array;

    /**
     * Mengubah menunggu atau berjalan menjadi berjalan. Job yang sudah selesai atau gagal tidak diambil lagi, sehingga
     * dijalankan dua kali tidak menggandakan pekerjaan. Null bila tidak ada yang diambil.
     *
     * @return JobRow|null
     */
    public function claim(string $id, DateTimeImmutable $at): ?array;

    public function finish(string $id, string $path, int $size, DateTimeImmutable $at, DateTimeImmutable $expiresAt): void;

    public function fail(string $id, string $message, DateTimeImmutable $at): void;

    /** Path berkas di disk laporan (null bila tidak ada). */
    public function filePath(string $id): ?string;

    /**
     * Berkas yang sudah lewat masa simpan; baris tetap ada, hanya berkas yang dilepas.
     *
     * @return list<array{id: string, path: string}>
     */
    public function expired(DateTimeImmutable $now, int $limit): array;

    public function releaseFile(string $id): void;
}

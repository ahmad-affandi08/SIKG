<?php

declare(strict_types=1);

namespace Src\Reporting\Application;

/**
 * Penyimpanan berkas laporan di disk privat (bukan folder publik, tanpa symlink). Isi dienkripsi aplikasi karena dapat
 * memuat nama pasien (LAP-09).
 */
interface ReportFileStore
{
    /** @return string path relatif di disk */
    public function put(string $jobId, string $extension, string $contents): string;

    public function read(string $path): ?string;

    public function delete(string $path): void;
}

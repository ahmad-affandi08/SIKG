<?php

declare(strict_types=1);

namespace Src\Reporting\Infrastructure;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Src\Reporting\Application\ReportExportService;
use Src\Reporting\Application\ReportJobStore;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TenantContext;
use Throwable;

/**
 * Membuat satu berkas laporan. Pendek dan idempoten: bila sudah selesai atau gagal, job tidak mengerjakan ulang. Hanya id
 * catatan dan klinik yang dibawa job, tanpa data pasien. Agregasi dibatasi rentang 366 hari dan baris per tabel, sehingga
 * muat dalam batas waktu hosting tanpa pemecahan lebih lanjut.
 */
final class BuildReportFileJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    // Hasil deterministik; galat ditandai gagal dan pengguna meminta ulang, sehingga tidak ada percobaan otomatis.
    public int $tries = 1;

    /** @var list<int> */
    public array $backoff = [];

    public int $timeout = 55;

    public function __construct(public readonly string $jobId, public readonly string $clinicId, public readonly ?string $branchId) {}

    public function handle(ReportExportService $exports, TenantContext $tenant): void
    {
        $tenant->runAs($this->clinicId, $this->branchId, fn () => $exports->process($this->jobId));
    }

    public function failed(Throwable $e): void
    {
        app(TenantContext::class)->runAs($this->clinicId, $this->branchId, function (): void {
            app(ReportJobStore::class)->fail($this->jobId, 'Pembuatan berkas dihentikan. Minta ulang berkas ini.', app(Clock::class)->now());
        });
    }
}

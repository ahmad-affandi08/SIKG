<?php

declare(strict_types=1);

namespace Src\Reporting\Application;

use DateTimeImmutable;
use Illuminate\Support\Facades\Log;
use Src\Reporting\Domain\ExportFormat;
use Src\Reporting\Domain\InvalidReportRequest;
use Src\Reporting\Domain\JobStatus;
use Src\Reporting\Domain\ReportKey;
use Src\Reporting\Domain\ReportScope;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Transaction;
use Throwable;

/**
 * Ekspor laporan ke PDF dan XLSX lewat antrean: permintaan membuat catatan `report_jobs` berstatus menunggu, job
 * mengambilnya (berjalan), membuat berkas, lalu selesai atau gagal. Pengguna memantau lewat polling.
 *
 * @phpstan-import-type JobRow from ReportJobStore
 */
final readonly class ReportExportService
{
    /** Masa simpan berkas ekspor (hari) sebelum dilepas oleh perintah pemangkasan. */
    public const RETENTION_DAYS = 7;

    /** Job yang berjalan lebih lama dari ini dianggap mati (pekerja dihentikan hosting) dan dinyatakan gagal. */
    public const STALE_RUNNING_SECONDS = 600;

    public function __construct(
        private ReportService $reports,
        private ReportJobStore $jobs,
        private ReportFileStore $files,
        private ReportRenderer $renderer,
        private ReportJobDispatcher $dispatcher,
        private AuditLogger $audit,
        private Clock $clock,
        private Transaction $tx,
    ) {}

    /**
     * @return JobRow
     */
    public function request(ReportViewer $viewer, ReportKey $key, ReportFilters $filters, ExportFormat $format): array
    {
        $query = $this->reports->query($viewer, $key, $filters);
        // Catatan berkas dan jejak audit dalam satu transaksi; pekerjaan antrean baru dikirim setelah commit.
        $job = $this->tx->run(function () use ($viewer, $key, $format, $filters, $query): array {
            $job = $this->jobs->create($key, $format, $query->scope, $filters->toArray(), $viewer->userId, $this->clock->now());
            $this->audit->record(new AuditEntry($viewer->userId, 'report.export_requested', 'report_job', $job['id'], null, [
                'report' => $key->code(), 'format' => $format->value, 'from' => $filters->range->from, 'to' => $filters->range->to,
            ]));

            return $job;
        });
        $this->dispatcher->dispatch($job['id']);

        return $this->status($viewer, $job['id']);
    }

    /**
     * Status berkas milik pengguna peminta.
     *
     * @return JobRow
     */
    public function status(ReportViewer $viewer, string $jobId): array
    {
        $job = $this->owned($viewer, $jobId);
        $this->assertStillAllowed($viewer, $job);
        $now = $this->clock->now();
        if ($job['status'] === JobStatus::Running->value && $job['started_at'] !== null
            && $now->getTimestamp() - (new DateTimeImmutable($job['started_at']))->getTimestamp() > self::STALE_RUNNING_SECONDS) {
            $this->jobs->fail($jobId, 'Pembuatan berkas terhenti. Minta ulang berkas ini.', $now);
            $job = $this->owned($viewer, $jobId);
        }

        return $job;
    }

    /**
     * Isi berkas siap unduh.
     *
     * @return array{name: string, mime: string, contents: string}
     */
    public function download(ReportViewer $viewer, string $jobId): array
    {
        $job = $this->owned($viewer, $jobId);
        $this->assertStillAllowed($viewer, $job);
        if ($job['status'] !== JobStatus::Done->value) {
            throw new InvalidReportRequest('report.job_not_ready', 'Berkas laporan belum siap.');
        }
        $path = $this->jobs->filePath($jobId);
        $contents = $path === null ? null : $this->files->read($path);
        if ($contents === null) {
            throw new InvalidReportRequest('report.job_expired', 'Berkas sudah kedaluwarsa. Minta ulang berkas laporan.');
        }
        $format = ExportFormat::from($job['format']);
        $key = ReportKey::from($job['report_key']);
        $this->audit->record(new AuditEntry($viewer->userId, 'report.downloaded', 'report_job', $jobId, null, ['report' => $key->code(), 'format' => $format->value]));

        $range = $job['filters'];
        $name = sprintf('%s-%s_%s_%s.%s', strtolower($key->code()), $key->value, (string) ($range['from'] ?? ''), (string) ($range['to'] ?? ''), $format->extension());

        return ['name' => $name, 'mime' => $format->mimeType(), 'contents' => $contents];
    }

    /**
     * Dipanggil job antrean. Idempoten: job yang sudah selesai atau gagal tidak dikerjakan ulang. Galat tidak dilempar
     * ke antrean; berkas ditandai gagal dengan pesan yang aman dan pengguna dapat memintanya lagi.
     */
    public function process(string $jobId): void
    {
        $job = $this->jobs->claim($jobId, $this->clock->now());
        if ($job === null) {
            return;
        }

        try {
            $key = ReportKey::from($job['report_key']);
            $format = ExportFormat::from($job['format']);
            $query = new ReportQuery(ReportFilters::fromArray($job['filters']), ReportScope::from($job['scope']), $job['requested_by']);
            $contents = $this->renderer->render($format, $this->reports->runQuery($key, $query));
            $path = $this->files->put($jobId, $format->extension(), $contents);
            $now = $this->clock->now();
            $this->jobs->finish($jobId, $path, strlen($contents), $now, $now->modify('+'.self::RETENTION_DAYS.' days'));
        } catch (Throwable $e) {
            // Hanya kelas galat dan id berkas yang dicatat: pesan galat basis data dapat memuat nilai baris (PHI).
            Log::error('report.export_failed', ['job' => $jobId, 'exception' => $e::class]);
            $this->jobs->fail($jobId, 'Berkas laporan gagal dibuat. Coba minta ulang; hubungi pengelola teknis bila berulang.', $this->clock->now());
        }
    }

    /**
     * Melepas berkas yang sudah lewat masa simpan. Mengembalikan jumlah berkas yang dilepas.
     */
    public function pruneExpired(int $limit = 200): int
    {
        $count = 0;
        foreach ($this->jobs->expired($this->clock->now(), $limit) as $row) {
            $this->files->delete($row['path']);
            $this->jobs->releaseFile($row['id']);
            $count++;
        }

        return $count;
    }

    /**
     * Izin dapat dicabut atau dipersempit setelah berkas diminta; berkas lama tidak boleh melampaui izin sekarang.
     *
     * @param  JobRow  $job
     */
    private function assertStillAllowed(ReportViewer $viewer, array $job): void
    {
        $current = $viewer->scopeFor(ReportKey::from($job['report_key']));
        if ($current === null || ($current === ReportScope::Own && $job['scope'] === ReportScope::Full->value)) {
            throw new InvalidReportRequest('report.forbidden', 'Anda tidak lagi berhak atas laporan ini.');
        }
    }

    /**
     * @return JobRow
     */
    private function owned(ReportViewer $viewer, string $jobId): array
    {
        $job = $this->jobs->find($jobId);
        if ($job === null || $job['requested_by'] !== $viewer->userId) {
            throw new InvalidReportRequest('report.job_not_found', 'Berkas laporan tidak ditemukan.');
        }

        return $job;
    }
}

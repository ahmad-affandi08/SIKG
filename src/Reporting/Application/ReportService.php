<?php

declare(strict_types=1);

namespace Src\Reporting\Application;

use DateTimeImmutable;
use Src\Reporting\Domain\DateRange;
use Src\Reporting\Domain\InvalidReportRequest;
use Src\Reporting\Domain\ReportKey;
use Src\Reporting\Domain\ReportResult;
use Src\Reporting\Domain\ReportScope;

/**
 * LAP-01 sampai LAP-10: daftar laporan yang boleh dilihat, penyaring bawaan, dan penjalanan laporan setelah pemeriksaan
 * akses. Laporan keuangan dan klinis memakai kueri agregasi di server (NFR-01).
 */
final class ReportService
{
    /** @var array<string, Report> */
    private array $reports = [];

    /**
     * @param  iterable<Report>  $reports
     */
    public function __construct(iterable $reports, private readonly ReportLookups $lookups)
    {
        foreach ($reports as $report) {
            $this->reports[$report->key()->value] = $report;
        }
    }

    /**
     * @return list<array{key: string, code: string, title: string, description: string, group: string}>
     */
    public function available(ReportViewer $viewer): array
    {
        return array_map(fn (ReportKey $k) => [
            'key' => $k->value, 'code' => $k->code(), 'title' => $k->title(), 'description' => $k->description(), 'group' => $k->group(),
        ], $viewer->visibleReports());
    }

    public function keyFor(string $slug): ReportKey
    {
        return ReportKey::tryFrom($slug) ?? throw new InvalidReportRequest('report.not_found', 'Laporan tidak ditemukan.');
    }

    /**
     * Penyaring dari masukan pengguna dengan bawaan: hari ini untuk ringkasan harian, sebulan berjalan untuk lainnya.
     *
     * @param  array<string, mixed>  $input
     */
    public function filters(ReportKey $key, array $input): ReportFilters
    {
        $today = $this->lookups->today();
        $from = is_string($input['from'] ?? null) && $input['from'] !== '' ? $input['from'] : null;
        $to = is_string($input['to'] ?? null) && $input['to'] !== '' ? $input['to'] : null;
        if ($from === null && $to === null) {
            $from = match (true) {
                $key->singleDayDefault() => $today,
                $key === ReportKey::Receivables => (new DateTimeImmutable($today))->modify('-364 days')->format('Y-m-d'),
                default => substr($today, 0, 8).'01',
            };
            $to = $today;
        }
        $from ??= $to;
        $to ??= $from;

        $string = fn (string $name): ?string => is_string($input[$name] ?? null) && $input[$name] !== '' ? $input[$name] : null;

        return new ReportFilters(
            DateRange::between((string) $from, (string) $to),
            $key->usesDoctorFilter() ? $string('doctor_id') : null,
            $key->usesMethodFilter() ? $string('method_id') : null,
            $key->usesGrain() && ($input['grain'] ?? null) === ReportFilters::GRAIN_MONTH ? ReportFilters::GRAIN_MONTH : ReportFilters::GRAIN_DAY,
        );
    }

    public function query(ReportViewer $viewer, ReportKey $key, ReportFilters $filters): ReportQuery
    {
        $scope = $viewer->scopeFor($key);
        if ($scope === null) {
            throw new InvalidReportRequest('report.forbidden', 'Anda tidak memiliki izin untuk laporan ini.');
        }

        return new ReportQuery($filters, $scope, $viewer->userId);
    }

    public function run(ReportViewer $viewer, ReportKey $key, ReportFilters $filters): ReportResult
    {
        return $this->runQuery($key, $this->query($viewer, $key, $filters));
    }

    /**
     * Menjalankan laporan untuk permintaan yang akses dan cakupannya sudah diputuskan (berkas ekspor lewat antrean).
     */
    public function runQuery(ReportKey $key, ReportQuery $query): ReportResult
    {
        $report = $this->reports[$key->value] ?? throw new InvalidReportRequest('report.not_found', 'Laporan tidak ditemukan.');

        return $report->run($query);
    }

    /**
     * Pilihan penyaring untuk halaman laporan. Pengguna dengan cakupan "sendiri" tidak memilih dokter: laporan sudah
     * dibatasi ke dirinya.
     *
     * @return array{doctors: list<array{id: string, name: string}>, methods: list<array{id: string, name: string}>}
     */
    public function options(ReportViewer $viewer, ReportKey $key): array
    {
        $full = $viewer->scopeFor($key) === ReportScope::Full;

        return [
            'doctors' => $key->usesDoctorFilter() && $full ? $this->lookups->doctors() : [],
            'methods' => $key->usesMethodFilter() ? $this->lookups->paymentMethods() : [],
        ];
    }
}

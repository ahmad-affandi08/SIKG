<?php

declare(strict_types=1);

namespace Src\Reporting\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Reporting\Application\ReportExportService;
use Src\Reporting\Application\ReportService;
use Src\Reporting\Domain\ExportFormat;
use Src\Reporting\Domain\ReportScope;
use Src\Reporting\Presentation\Http\ReportViewers;
use Src\Reporting\Presentation\Http\Requests\ExportReportRequest;
use Src\Reporting\Presentation\Http\Requests\ReportFilterRequest;

/**
 * LAP-01 sampai LAP-10: daftar laporan, tampilan satu laporan, dan permintaan ekspor PDF atau Excel. Izin dasar di route
 * (`reports.open`); laporan dan cakupan yang boleh dilihat diputuskan Application dari izin pengguna (M9).
 */
final class ReportController
{
    public function index(Request $request, ReportService $reports): Response
    {
        return Inertia::render('Reports/Index', [
            'reports' => $reports->available(ReportViewers::from($request)),
        ]);
    }

    public function show(ReportFilterRequest $request, ReportService $reports, string $key): Response
    {
        $viewer = ReportViewers::from($request);
        $reportKey = $reports->keyFor($key);
        $filters = $reports->filters($reportKey, $request->validated());
        $result = $reports->run($viewer, $reportKey, $filters);

        return Inertia::render('Reports/Show', [
            'report' => $result->toArray(),
            'filters' => [
                'from' => $filters->range->from,
                'to' => $filters->range->to,
                'doctor_id' => $filters->doctorId ?? '',
                'method_id' => $filters->methodId ?? '',
                'grain' => $filters->grain,
            ],
            'options' => [
                ...$reports->options($viewer, $reportKey),
                'grain' => $reportKey->usesGrain(),
            ],
            'scope' => ($viewer->scopeFor($reportKey) ?? ReportScope::Own)->value,
            'description' => $reportKey->description(),
        ]);
    }

    public function export(ExportReportRequest $request, ReportService $reports, ReportExportService $exports, string $key): JsonResponse
    {
        $viewer = ReportViewers::from($request);
        $reportKey = $reports->keyFor($key);
        $validated = $request->validated();
        $filters = $reports->filters($reportKey, $validated);
        $job = $exports->request($viewer, $reportKey, $filters, ExportFormat::from((string) $validated['format']));

        return response()->json(['data' => $job, 'meta' => (object) []], 202);
    }
}

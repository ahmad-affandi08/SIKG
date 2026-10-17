<?php

declare(strict_types=1);

namespace Src\Reporting\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Reporting\Application\ReportExportService;
use Src\Reporting\Domain\JobStatus;
use Src\Reporting\Presentation\Http\ReportViewers;
use Symfony\Component\HttpFoundation\Response;

/**
 * Status dan unduhan berkas laporan (`GET /internal-api/report-jobs/{id}`, SYS-07). Hanya pemintanya yang dapat melihat.
 */
final class ReportJobController
{
    public function status(Request $request, ReportExportService $exports, string $job): JsonResponse
    {
        $row = $exports->status(ReportViewers::from($request), $job);

        return response()->json(['data' => [
            ...$row,
            'status_label' => JobStatus::from($row['status'])->label(),
            'download_url' => $row['status'] === JobStatus::Done->value && $row['has_file'] ? "/report-jobs/{$job}/download" : null,
        ], 'meta' => (object) []])->header('Cache-Control', 'private, no-store');
    }

    public function download(Request $request, ReportExportService $exports, string $job): Response
    {
        $file = $exports->download(ReportViewers::from($request), $job);

        return response($file['contents'], 200, [
            'Content-Type' => $file['mime'],
            'Content-Disposition' => 'attachment; filename="'.preg_replace('/[^A-Za-z0-9._-]/', '_', $file['name']).'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

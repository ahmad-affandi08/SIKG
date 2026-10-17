<?php

declare(strict_types=1);

namespace Src\Reporting;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Src\Reporting\Application\DoctorFeeStatementSource;
use Src\Reporting\Application\ReportExportService;
use Src\Reporting\Application\ReportFileStore;
use Src\Reporting\Application\ReportJobDispatcher;
use Src\Reporting\Application\ReportJobStore;
use Src\Reporting\Application\ReportLookups;
use Src\Reporting\Application\ReportRenderer;
use Src\Reporting\Application\ReportService;
use Src\Reporting\Contracts\OwnerDashboard;
use Src\Reporting\Infrastructure\Console\PruneReportFilesCommand;
use Src\Reporting\Infrastructure\EloquentReportJobStore;
use Src\Reporting\Infrastructure\Export\DelegatingReportRenderer;
use Src\Reporting\Infrastructure\PrivateReportFileStore;
use Src\Reporting\Infrastructure\Queries\DoctorFeeQueries;
use Src\Reporting\Infrastructure\Queries\EloquentOwnerDashboard;
use Src\Reporting\Infrastructure\Queries\QueryReportLookups;
use Src\Reporting\Infrastructure\QueueReportJobDispatcher;
use Src\Reporting\Infrastructure\Reports\CashClosingReport;
use Src\Reporting\Infrastructure\Reports\DailySummaryReport;
use Src\Reporting\Infrastructure\Reports\DoctorFeesReport;
use Src\Reporting\Infrastructure\Reports\ProceduresReport;
use Src\Reporting\Infrastructure\Reports\ProfitLossReport;
use Src\Reporting\Infrastructure\Reports\ReceivablesReport;
use Src\Reporting\Infrastructure\Reports\RecordCompletenessReport;
use Src\Reporting\Infrastructure\Reports\RevenueReport;
use Src\Reporting\Infrastructure\Reports\StockReport;
use Src\Reporting\Infrastructure\Reports\UserActivityReport;
use Src\Reporting\Infrastructure\Reports\VisitsReport;
use Src\Shared\Application\AuditLogger;

final class ReportingServiceProvider extends ServiceProvider
{
    private const REPORTS = [
        DailySummaryReport::class, RevenueReport::class, DoctorFeesReport::class, VisitsReport::class, ProceduresReport::class,
        ReceivablesReport::class, StockReport::class, CashClosingReport::class, RecordCompletenessReport::class, UserActivityReport::class, ProfitLossReport::class,
    ];

    public function register(): void
    {
        $this->app->bind(ReportLookups::class, QueryReportLookups::class);
        $this->app->bind(DoctorFeeStatementSource::class, DoctorFeeQueries::class);
        $this->app->bind(OwnerDashboard::class, EloquentOwnerDashboard::class);
        $this->app->bind(ReportJobStore::class, EloquentReportJobStore::class);
        $this->app->bind(ReportFileStore::class, PrivateReportFileStore::class);
        $this->app->bind(ReportRenderer::class, DelegatingReportRenderer::class);
        $this->app->bind(ReportJobDispatcher::class, QueueReportJobDispatcher::class);

        $this->app->tag(self::REPORTS, 'sikg.reports');
        $this->app->bind(ReportService::class, fn ($app) => new ReportService($app->tagged('sikg.reports'), $app->make(ReportLookups::class), $app->make(AuditLogger::class)));
        $this->app->bind(ReportExportService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Migrations');

        // Pintu masuk halaman laporan: report.view, atau stock.manage untuk Perawat yang hanya berhak atas laporan stok
        // (PRD 11, LAP-07). Laporan dan cakupan per pengguna diputuskan di Application.
        Gate::define('reports.open', fn (Authorizable $user): bool => $user->can('report.view') || $user->can('stock.manage'));

        Route::middleware(['web', 'auth'])
            ->group(__DIR__.'/Presentation/routes.php');

        if ($this->app->runningInConsole()) {
            $this->commands([PruneReportFilesCommand::class]);
        }
    }
}

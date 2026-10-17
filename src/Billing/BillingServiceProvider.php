<?php

declare(strict_types=1);

namespace Src\Billing;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Src\Billing\Application\BillingNumbering;
use Src\Billing\Application\CashRegister;
use Src\Billing\Application\InvoiceDrafting;
use Src\Billing\Application\InvoiceListing;
use Src\Billing\Contracts\InvoiceClaimGateway;
use Src\Billing\Contracts\InvoiceLookup;
use Src\Billing\Domain\InstallmentRepository;
use Src\Billing\Domain\InvoiceRepository;
use Src\Billing\Infrastructure\BillingInvoiceClaimGateway;
use Src\Billing\Infrastructure\ClinicBillingNumbering;
use Src\Billing\Infrastructure\Eloquent\InvoiceModel;
use Src\Billing\Infrastructure\EloquentCashRegister;
use Src\Billing\Infrastructure\EloquentInstallmentRepository;
use Src\Billing\Infrastructure\EloquentInvoiceListing;
use Src\Billing\Infrastructure\EloquentInvoiceRepository;
use Src\Clinical\Domain\Events\EncounterClosed;
use Src\Clinical\Domain\Events\ProcedurePerformed;
use Src\Clinical\Domain\Events\ProcedureRetracted;
use Src\Pharmacy\Domain\Events\MedicineDispensed;
use Src\Pharmacy\Domain\Events\MedicineReturned;
use Src\Shared\Application\TenantContext;
use Src\Shared\Infrastructure\ModelPatientMover;

final class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind('sikg.patient-mover.invoices', fn ($app) => new ModelPatientMover('Tagihan', InvoiceModel::class, $app->make(TenantContext::class)));
        $this->app->tag(['sikg.patient-mover.invoices'], 'sikg.patient-movers');
        $this->app->singleton(InvoiceRepository::class, EloquentInvoiceRepository::class);
        $this->app->singleton(InstallmentRepository::class, EloquentInstallmentRepository::class);
        $this->app->singleton(BillingNumbering::class, ClinicBillingNumbering::class);
        $this->app->singleton(CashRegister::class, EloquentCashRegister::class);
        $this->app->singleton(InvoiceListing::class, EloquentInvoiceListing::class);
        $this->app->singleton(InvoiceLookup::class, EloquentInvoiceListing::class);
        $this->app->singleton(InvoiceClaimGateway::class, BillingInvoiceClaimGateway::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Migrations');

        // KSR-01: item tagihan dari tindakan dan obat pada kunjungan; terbit saat kunjungan ditutup.
        Event::listen(ProcedurePerformed::class, [InvoiceDrafting::class, 'procedurePerformed']);
        Event::listen(ProcedureRetracted::class, [InvoiceDrafting::class, 'procedureRetracted']);
        Event::listen(MedicineDispensed::class, [InvoiceDrafting::class, 'medicineDispensed']);
        Event::listen(MedicineReturned::class, [InvoiceDrafting::class, 'medicineReturned']);
        Event::listen(EncounterClosed::class, [InvoiceDrafting::class, 'encounterClosed']);

        Route::middleware(['web', 'auth'])
            ->group(__DIR__.'/Presentation/routes.php');
    }
}

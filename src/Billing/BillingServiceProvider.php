<?php

declare(strict_types=1);

namespace Src\Billing;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Src\Billing\Application\BillingNumbering;
use Src\Billing\Application\CashRegister;
use Src\Billing\Application\InvoiceDrafting;
use Src\Billing\Application\InvoiceListing;
use Src\Billing\Application\Loyalty\LoyaltyService;
use Src\Billing\Application\Online\OnlinePayments;
use Src\Billing\Application\Online\PaymentGateway;
use Src\Billing\Application\Online\WebhookReplayGuard;
use Src\Billing\Contracts\EncounterChargeGateway;
use Src\Billing\Contracts\InvoiceClaimGateway;
use Src\Billing\Contracts\InvoiceLookup;
use Src\Billing\Domain\Events\InvoiceVoided;
use Src\Billing\Domain\Events\PaymentReceived;
use Src\Billing\Domain\ExpenseCategoryDirectory;
use Src\Billing\Domain\ExpenseRepository;
use Src\Billing\Domain\InstallmentRepository;
use Src\Billing\Domain\InvoiceRepository;
use Src\Billing\Domain\Loyalty\LoyaltyRepository;
use Src\Billing\Domain\Loyalty\PackageRepository;
use Src\Billing\Domain\Loyalty\PromotionRepository;
use Src\Billing\Domain\Loyalty\VoucherRepository;
use Src\Billing\Domain\Online\OnlinePaymentRepository;
use Src\Billing\Domain\PromotionGuard;
use Src\Billing\Infrastructure\BillingEncounterChargeGateway;
use Src\Billing\Infrastructure\BillingInvoiceClaimGateway;
use Src\Billing\Infrastructure\ClinicBillingNumbering;
use Src\Billing\Infrastructure\Console\ReconcileOnlinePaymentsCommand;
use Src\Billing\Infrastructure\Eloquent\InvoiceModel;
use Src\Billing\Infrastructure\Eloquent\InvoicePromotionModel;
use Src\Billing\Infrastructure\Eloquent\PatientPackageModel;
use Src\Billing\Infrastructure\EloquentCashRegister;
use Src\Billing\Infrastructure\EloquentExpenseCategoryDirectory;
use Src\Billing\Infrastructure\EloquentExpenseRepository;
use Src\Billing\Infrastructure\EloquentInstallmentRepository;
use Src\Billing\Infrastructure\EloquentInvoiceListing;
use Src\Billing\Infrastructure\EloquentInvoiceRepository;
use Src\Billing\Infrastructure\Loyalty\EloquentLoyaltyRepository;
use Src\Billing\Infrastructure\Loyalty\EloquentPackageRepository;
use Src\Billing\Infrastructure\Loyalty\EloquentPromotionGuard;
use Src\Billing\Infrastructure\Loyalty\EloquentPromotionRepository;
use Src\Billing\Infrastructure\Loyalty\EloquentVoucherRepository;
use Src\Billing\Infrastructure\Loyalty\LoyaltyPatientMover;
use Src\Billing\Infrastructure\Master\ExpenseCategoryEntity;
use Src\Billing\Infrastructure\Master\VisitPackageEntity;
use Src\Billing\Infrastructure\Master\VoucherEntity;
use Src\Billing\Infrastructure\Online\CacheWebhookReplayGuard;
use Src\Billing\Infrastructure\Online\DokuGateway;
use Src\Billing\Infrastructure\Online\EloquentOnlinePaymentRepository;
use Src\Billing\Infrastructure\Seeders\ExpenseCategorySeeder;
use Src\Billing\Infrastructure\Setup\BillingSetupSteps;
use Src\Clinical\Domain\Events\EncounterClosed;
use Src\Clinical\Domain\Events\ProcedurePerformed;
use Src\Clinical\Domain\Events\ProcedureRetracted;
use Src\Pharmacy\Domain\Events\MedicineDispensed;
use Src\Pharmacy\Domain\Events\MedicineReturned;
use Src\Shared\Application\Master\MasterEntityRegistry;
use Src\Shared\Application\Setup\SetupRegistry;
use Src\Shared\Application\TenantContext;
use Src\Shared\Domain\IdGenerator;
use Src\Shared\Infrastructure\ModelPatientMover;

final class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind('sikg.patient-mover.invoices', fn ($app) => new ModelPatientMover('Tagihan', InvoiceModel::class, $app->make(TenantContext::class)));
        $this->app->bind('sikg.patient-mover.packages', fn ($app) => new ModelPatientMover('Paket kunjungan', PatientPackageModel::class, $app->make(TenantContext::class)));
        $this->app->bind('sikg.patient-mover.promotions', fn ($app) => new ModelPatientMover('Promosi tagihan', InvoicePromotionModel::class, $app->make(TenantContext::class)));
        $this->app->bind('sikg.patient-mover.loyalty', fn ($app) => new LoyaltyPatientMover($app->make(TenantContext::class), $app->make(IdGenerator::class)));
        $this->app->tag(['sikg.patient-mover.invoices', 'sikg.patient-mover.packages', 'sikg.patient-mover.promotions', 'sikg.patient-mover.loyalty'], 'sikg.patient-movers');
        $this->app->singleton(InvoiceRepository::class, EloquentInvoiceRepository::class);
        $this->app->singleton(InstallmentRepository::class, EloquentInstallmentRepository::class);
        $this->app->singleton(BillingNumbering::class, ClinicBillingNumbering::class);
        $this->app->singleton(CashRegister::class, EloquentCashRegister::class);
        $this->app->singleton(InvoiceListing::class, EloquentInvoiceListing::class);
        $this->app->singleton(InvoiceLookup::class, EloquentInvoiceListing::class);
        $this->app->singleton(InvoiceClaimGateway::class, BillingInvoiceClaimGateway::class);
        $this->app->singleton(EncounterChargeGateway::class, BillingEncounterChargeGateway::class);
        $this->app->singleton(ExpenseRepository::class, EloquentExpenseRepository::class);
        $this->app->singleton(VoucherRepository::class, EloquentVoucherRepository::class);
        $this->app->singleton(PromotionRepository::class, EloquentPromotionRepository::class);
        $this->app->singleton(LoyaltyRepository::class, EloquentLoyaltyRepository::class);
        $this->app->singleton(PackageRepository::class, EloquentPackageRepository::class);
        $this->app->singleton(PromotionGuard::class, EloquentPromotionGuard::class);
        $this->app->singleton(ExpenseCategoryDirectory::class, EloquentExpenseCategoryDirectory::class);
        $this->app->singleton(OnlinePaymentRepository::class, EloquentOnlinePaymentRepository::class);
        $this->app->singleton(PaymentGateway::class, DokuGateway::class);
        $this->app->singleton(WebhookReplayGuard::class, CacheWebhookReplayGuard::class);
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

        // P2-08: biaya operasional dilihat oleh pemegang laporan keuangan penuh dan dicatat oleh pemegang izin uang keluar
        // (refund), yaitu Pemilik dan Admin; tidak menambah izin baru pada matriks hak akses.
        Gate::define('expense.view', fn (Authenticatable $user): bool => Gate::forUser($user)->allows('report.financial'));
        Gate::define('expense.manage', fn (Authenticatable $user): bool => Gate::forUser($user)->allows('payment.refund'));

        $this->app->make(MasterEntityRegistry::class)->register($this->app->make(ExpenseCategoryEntity::class));

        // MST-10: langkah dan data awal yang dimiliki konteks ini untuk halaman Penyiapan awal.
        $setup = $this->app->make(SetupRegistry::class);
        $setup->registerSteps(new BillingSetupSteps);
        $setup->registerStarter(new ExpenseCategorySeeder);
        $this->app->make(MasterEntityRegistry::class)->register($this->app->make(VoucherEntity::class));
        $this->app->make(MasterEntityRegistry::class)->register($this->app->make(VisitPackageEntity::class));

        // P3-06: poin didapat saat tagihan lunas; promosi, poin, dan paket dibalik saat tagihan dibatalkan.
        Event::listen(PaymentReceived::class, [LoyaltyService::class, 'paymentReceived']);
        Event::listen(InvoiceVoided::class, [LoyaltyService::class, 'invoiceVoided']);

        // P3-05: QR yang masih menunggu dibatalkan bersama tagihannya; rekonsiliasi dijalankan perintah terjadwal.
        Event::listen(InvoiceVoided::class, [OnlinePayments::class, 'invoiceVoided']);

        if ($this->app->runningInConsole()) {
            $this->commands([ReconcileOnlinePaymentsCommand::class]);
        }

        Route::middleware(['web', 'auth'])
            ->group(__DIR__.'/Presentation/routes.php');

        // P3-05: webhook penyedia pembayaran, tanpa sesi.
        Route::group([], __DIR__.'/Presentation/routes-webhook.php');
    }
}

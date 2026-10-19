<?php

declare(strict_types=1);

namespace Src\Clinic;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Src\Clinic\Application\Doctors\DoctorAdministration;
use Src\Clinic\Application\Profile\ClinicProfileService;
use Src\Clinic\Application\Settings\ClinicSettingsService;
use Src\Clinic\Contracts\BranchDirectory;
use Src\Clinic\Contracts\ChairDirectory;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\Clinic\Contracts\ClinicProvisioner;
use Src\Clinic\Contracts\DiscountLimits;
use Src\Clinic\Contracts\DoctorDirectory;
use Src\Clinic\Contracts\DocumentTemplateCatalog;
use Src\Clinic\Contracts\InvoiceNumbering;
use Src\Clinic\Contracts\LetterheadProvider;
use Src\Clinic\Contracts\LoginBranding;
use Src\Clinic\Contracts\MedicalRecordNumbering;
use Src\Clinic\Contracts\PaymentMethodCatalog;
use Src\Clinic\Contracts\ProcedureCatalog;
use Src\Clinic\Contracts\ReminderSettings;
use Src\Clinic\Contracts\ServiceProfileLookup;
use Src\Clinic\Contracts\TaxRate;
use Src\Clinic\Contracts\ToothConditionCatalog;
use Src\Clinic\Infrastructure\ClinicDiscountLimits;
use Src\Clinic\Infrastructure\ClinicInvoiceNumbering;
use Src\Clinic\Infrastructure\ClinicMedicalRecordNumbering;
use Src\Clinic\Infrastructure\ClinicReminderSettings;
use Src\Clinic\Infrastructure\ClinicServiceProfileLookup;
use Src\Clinic\Infrastructure\ClinicStaffDestinations;
use Src\Clinic\Infrastructure\ClinicTaxRate;
use Src\Clinic\Infrastructure\EloquentBranchDirectory;
use Src\Clinic\Infrastructure\EloquentChairDirectory;
use Src\Clinic\Infrastructure\EloquentClinicCalendar;
use Src\Clinic\Infrastructure\EloquentClinicProfileService;
use Src\Clinic\Infrastructure\EloquentClinicProvisioner;
use Src\Clinic\Infrastructure\EloquentClinicSettingsService;
use Src\Clinic\Infrastructure\EloquentDoctorAdministration;
use Src\Clinic\Infrastructure\EloquentDoctorDirectory;
use Src\Clinic\Infrastructure\EloquentDocumentTemplateCatalog;
use Src\Clinic\Infrastructure\EloquentLetterheadProvider;
use Src\Clinic\Infrastructure\EloquentLoginBranding;
use Src\Clinic\Infrastructure\EloquentPaymentMethodCatalog;
use Src\Clinic\Infrastructure\EloquentProcedureCatalog;
use Src\Clinic\Infrastructure\EloquentToothConditionCatalog;
use Src\Clinic\Infrastructure\Master\BranchEntity;
use Src\Clinic\Infrastructure\Master\ChairEntity;
use Src\Clinic\Infrastructure\Master\DocumentTemplateEntity;
use Src\Clinic\Infrastructure\Master\PaymentMethodEntity;
use Src\Clinic\Infrastructure\Master\ProcedureEntity;
use Src\Clinic\Infrastructure\Master\ToothConditionEntity;
use Src\Clinic\Infrastructure\Seeders\NationalHolidaySeeder;
use Src\Clinic\Infrastructure\Seeders\PaymentMethodSeeder;
use Src\Clinic\Infrastructure\Seeders\ProcedureSeeder;
use Src\Clinic\Infrastructure\Setup\ClinicSetupSteps;
use Src\Shared\Application\Master\MasterEntityRegistry;
use Src\Shared\Application\Notifications\StaffDestinations;
use Src\Shared\Application\Setup\SetupRegistry;

final class ClinicServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BranchDirectory::class, EloquentBranchDirectory::class);
        $this->app->singleton(ClinicProvisioner::class, EloquentClinicProvisioner::class);
        $this->app->singleton(ClinicSettingsService::class, EloquentClinicSettingsService::class);
        $this->app->singleton(DoctorDirectory::class, EloquentDoctorDirectory::class);
        $this->app->singleton(LetterheadProvider::class, EloquentLetterheadProvider::class);
        $this->app->singleton(LoginBranding::class, EloquentLoginBranding::class);
        $this->app->singleton(StaffDestinations::class, ClinicStaffDestinations::class);
        $this->app->singleton(ReminderSettings::class, ClinicReminderSettings::class);
        $this->app->singleton(ChairDirectory::class, EloquentChairDirectory::class);
        $this->app->singleton(ClinicCalendar::class, EloquentClinicCalendar::class);
        $this->app->singleton(MedicalRecordNumbering::class, ClinicMedicalRecordNumbering::class);
        $this->app->singleton(InvoiceNumbering::class, ClinicInvoiceNumbering::class);
        $this->app->singleton(DiscountLimits::class, ClinicDiscountLimits::class);
        $this->app->singleton(TaxRate::class, ClinicTaxRate::class);
        $this->app->singleton(ServiceProfileLookup::class, ClinicServiceProfileLookup::class);
        $this->app->singleton(PaymentMethodCatalog::class, EloquentPaymentMethodCatalog::class);
        $this->app->singleton(ToothConditionCatalog::class, EloquentToothConditionCatalog::class);
        $this->app->singleton(ProcedureCatalog::class, EloquentProcedureCatalog::class);
        $this->app->singleton(DocumentTemplateCatalog::class, EloquentDocumentTemplateCatalog::class);
        $this->app->singleton(ClinicProfileService::class, EloquentClinicProfileService::class);
        $this->app->singleton(DoctorAdministration::class, EloquentDoctorAdministration::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Migrations');

        $registry = $this->app->make(MasterEntityRegistry::class);
        $registry->register($this->app->make(ChairEntity::class));
        $registry->register($this->app->make(BranchEntity::class));
        $registry->register($this->app->make(ProcedureEntity::class));
        $registry->register($this->app->make(PaymentMethodEntity::class));
        $registry->register($this->app->make(ToothConditionEntity::class));
        $registry->register($this->app->make(DocumentTemplateEntity::class));

        // MST-10: langkah dan data awal yang dimiliki konteks ini untuk halaman Penyiapan awal.
        $setup = $this->app->make(SetupRegistry::class);
        $setup->registerSteps(new ClinicSetupSteps);
        $setup->registerStarter(new PaymentMethodSeeder);
        $setup->registerStarter(new ProcedureSeeder);
        $setup->registerStarter(new NationalHolidaySeeder);

        Route::middleware(['web', 'auth'])
            ->group(__DIR__.'/Presentation/routes.php');

        // Logo untuk halaman masuk: tanpa login.
        Route::middleware(['web'])
            ->group(__DIR__.'/Presentation/routes-public.php');
    }
}

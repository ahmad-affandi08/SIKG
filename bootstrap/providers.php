<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Src\Billing\BillingServiceProvider;
use Src\Clinic\ClinicServiceProvider;
use Src\Clinical\ClinicalServiceProvider;
use Src\IdentityAccess\IdentityAccessServiceProvider;
use Src\Patient\PatientServiceProvider;
use Src\Pharmacy\PharmacyServiceProvider;
use Src\Reporting\ReportingServiceProvider;
use Src\Scheduling\SchedulingServiceProvider;
use Src\Shared\Infrastructure\SharedServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    IdentityAccessServiceProvider::class,
    ClinicServiceProvider::class,
    PatientServiceProvider::class,
    SchedulingServiceProvider::class,
    ClinicalServiceProvider::class,
    PharmacyServiceProvider::class,
    BillingServiceProvider::class,
    ReportingServiceProvider::class,
];

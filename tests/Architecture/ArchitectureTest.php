<?php

declare(strict_types=1);

arch('Domain bebas framework')
    ->expect('Src\*\Domain')
    ->not->toUse(['Illuminate', 'Inertia', 'Src\*\Application', 'Src\*\Infrastructure', 'Src\*\Presentation']);

arch('Domain tidak memakai fungsi global berbahaya')
    ->expect('Src\*\Domain')
    ->not->toUse(['now', 'today', 'config', 'app', 'env', 'dd', 'dump', 'request', 'auth']);

arch('Application tidak memakai HTTP, Eloquent, atau Inertia (konteks inti)')
    ->expect(['Src\Patient\Application', 'Src\Scheduling\Application', 'Src\Clinical\Application', 'Src\Pharmacy\Application', 'Src\Billing\Application'])
    ->not->toUse(['Illuminate\Http', 'Illuminate\Database\Eloquent', 'Inertia', 'Src\*\Infrastructure', 'Src\*\Presentation']);

arch('Application Shared tidak memakai framework')
    ->expect('Src\Shared\Application')
    ->not->toUse(['Illuminate', 'Inertia', 'Src\Shared\Infrastructure']);

arch('Presentation hanya memanggil Application')
    ->expect('Src\*\Presentation')
    ->not->toUse(['Src\*\Infrastructure', 'Illuminate\Database\Eloquent']);

arch('Controller tipis dan final')
    ->expect('Src\*\Presentation\Http\Controllers')
    ->toBeClasses()
    ->toHaveSuffix('Controller')
    ->toBeFinal();

arch('Event domain immutable')
    ->expect('Src\*\Domain\Events')
    ->toBeReadonly();

arch('Tidak ada debug tertinggal')
    ->expect(['dd', 'dump', 'var_dump', 'ray'])
    ->not->toBeUsed();

arch('Model Eloquent hanya di Infrastructure')
    ->expect('Illuminate\Database\Eloquent\Model')
    // Pola wildcard tidak didukung oleh toOnlyBeUsedIn; daftar konteks ditulis eksplisit.
    ->toOnlyBeUsedIn(['Src\\Shared\\Infrastructure', 'Src\\IdentityAccess\\Infrastructure', 'Src\\Clinic\\Infrastructure', 'Src\\Patient\\Infrastructure', 'Src\\Scheduling\\Infrastructure', 'Src\\Clinical\\Infrastructure', 'Src\\Pharmacy\\Infrastructure', 'Src\\Billing\\Infrastructure', 'Src\\Reporting\\Infrastructure', 'Src\\Integration\\Infrastructure', 'Src\\Insurance\\Infrastructure', 'Src\\Lab\\Infrastructure', 'Src\\Orthodontics\\Infrastructure']);

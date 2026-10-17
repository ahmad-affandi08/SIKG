<?php

// TEMPLAT: salin ke tests/Architecture/ArchitectureTest.php

declare(strict_types=1);

arch('Domain bebas framework')
    ->expect('Src\*\Domain')
    ->not->toUse(['Illuminate', 'Inertia', 'Src\*\Application', 'Src\*\Infrastructure', 'Src\*\Presentation'])
    ->ignoring('Src\Shared');

arch('Domain tidak memakai fungsi global berbahaya')
    ->expect('Src\*\Domain')
    ->not->toUse(['now', 'today', 'config', 'app', 'env', 'dd', 'dump', 'request', 'auth']);

arch('Application tidak memakai HTTP, Eloquent, atau Inertia (konteks inti)')
    ->expect(['Src\Patient\Application', 'Src\Scheduling\Application', 'Src\Clinical\Application', 'Src\Pharmacy\Application', 'Src\Billing\Application'])
    ->not->toUse(['Illuminate\Http', 'Illuminate\Database\Eloquent', 'Inertia', 'Src\*\Infrastructure', 'Src\*\Presentation']);

arch('Presentation hanya memanggil Application')
    ->expect('Src\*\Presentation')
    ->not->toUse(['Src\*\Infrastructure', 'Illuminate\Database\Eloquent']);

arch('Controller tipis dan final')
    ->expect('Src\*\Presentation\Http\Controllers')
    ->toBeClasses()
    ->toHaveSuffix('Controller')
    ->toBeFinal();

arch('Value object dan event immutable')
    ->expect(['Src\*\Domain\Events'])
    ->toBeReadonly();

arch('Handler satu use case')
    ->expect('Src\*\Application')
    ->classes()
    ->toHaveSuffix('Handler')
    ->ignoring(['Src\*\Application\*\*Command', 'Src\*\Application\*\*Query', 'Src\*\Application\*\*Dto']);

arch('Tidak ada debug tertinggal')
    ->expect(['dd', 'dump', 'var_dump', 'ray'])
    ->not->toBeUsed();

arch('Model Eloquent hanya di Infrastructure')
    ->expect('Illuminate\Database\Eloquent\Model')
    ->toOnlyBeUsedIn(['Src\*\Infrastructure', 'App\Models']);

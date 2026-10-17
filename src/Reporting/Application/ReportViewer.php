<?php

declare(strict_types=1);

namespace Src\Reporting\Application;

use Src\Reporting\Domain\ReportKey;
use Src\Reporting\Domain\ReportScope;

/**
 * Pengguna yang meminta laporan beserta izinnya. Presentation membangunnya dari pengguna yang masuk; Application
 * menentukan laporan dan cakupan yang boleh dilihat (M9, matriks PRD 00 bagian 4).
 */
final readonly class ReportViewer
{
    /**
     * @param  list<string>  $permissions
     */
    public function __construct(public string $userId, public array $permissions) {}

    /**
     * Izin yang menentukan akses laporan; Presentation memeriksa tiap izin ini untuk pengguna yang masuk.
     *
     * @return list<string>
     */
    public static function relevantPermissions(): array
    {
        $all = [];
        foreach (ReportKey::cases() as $key) {
            $all = [...$all, ...$key->fullPermissions(), ...$key->ownPermissions()];
        }

        return array_values(array_unique($all));
    }

    public function scopeFor(ReportKey $key): ?ReportScope
    {
        if ($this->hasAny($key->fullPermissions())) {
            return ReportScope::Full;
        }

        return $this->hasAny($key->ownPermissions()) ? ReportScope::Own : null;
    }

    /**
     * @return list<ReportKey>
     */
    public function visibleReports(): array
    {
        return array_values(array_filter(ReportKey::cases(), fn (ReportKey $k) => $this->scopeFor($k) !== null));
    }

    /**
     * @param  list<string>  $needed
     */
    private function hasAny(array $needed): bool
    {
        return array_intersect($needed, $this->permissions) !== [];
    }
}

<?php

declare(strict_types=1);

namespace Src\Reporting\Infrastructure;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Src\Reporting\Application\ReportFileStore;
use Src\Shared\Application\PiiProtector;
use Src\Shared\Application\TenantContext;
use Src\Shared\Domain\IdGenerator;

/**
 * Berkas laporan di disk privat, selalu dienkripsi di aplikasi (LAP-09 memuat nama pasien). Tanpa symlink dan tanpa URL
 * publik; diunduh lewat controller yang memeriksa pemilik berkas.
 */
final readonly class PrivateReportFileStore implements ReportFileStore
{
    public function __construct(private PiiProtector $crypto, private IdGenerator $ids, private TenantContext $tenant) {}

    public function put(string $jobId, string $extension, string $contents): string
    {
        $clinic = $this->tenant->clinicId() ?? throw new RuntimeException('Konteks klinik kosong.');
        $path = "reports/{$clinic}/{$jobId}-{$this->ids->generate()}.{$extension}.enc";
        Storage::disk('private')->put($path, $this->crypto->encrypt(base64_encode($contents)));

        return $path;
    }

    public function read(string $path): ?string
    {
        $disk = Storage::disk('private');
        if (! $disk->exists($path)) {
            return null;
        }
        $decoded = base64_decode($this->crypto->decrypt((string) $disk->get($path)), true);

        return $decoded === false ? null : $decoded;
    }

    public function delete(string $path): void
    {
        Storage::disk('private')->delete($path);
    }
}

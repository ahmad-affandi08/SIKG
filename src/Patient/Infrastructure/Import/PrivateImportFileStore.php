<?php

declare(strict_types=1);

namespace Src\Patient\Infrastructure\Import;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Src\Patient\Application\Import\ImportFileStore;
use Src\Shared\Application\PiiProtector;
use Src\Shared\Domain\IdGenerator;

/**
 * Berkas impor memuat data pasien, jadi disimpan di disk privat dan dienkripsi di aplikasi (sikg.encrypt_files).
 * Salinan lokal untuk pembacaan hanya ada selama callback berjalan, lalu dihapus.
 */
final readonly class PrivateImportFileStore implements ImportFileStore
{
    public function __construct(private PiiProtector $pii, private IdGenerator $ids, private bool $encrypt) {}

    public function put(string $contents): string
    {
        $path = "imports/{$this->ids->generate()}.xlsx";
        Storage::disk('private')->put($path, $this->encrypt ? $this->pii->encrypt(base64_encode($contents)) : $contents);

        return $path;
    }

    public function withLocalCopy(string $path, callable $callback): mixed
    {
        $disk = Storage::disk('private');
        $stored = $disk->get($path);
        if ($stored === null) {
            throw new RuntimeException('Berkas impor tidak ditemukan.');
        }
        $contents = $this->encrypt ? (string) base64_decode($this->pii->decrypt($stored), true) : $stored;

        $tmp = "imports/tmp-{$this->ids->generate()}.xlsx";
        $disk->put($tmp, $contents);
        try {
            return $callback($disk->path($tmp));
        } finally {
            $disk->delete($tmp);
        }
    }

    public function delete(string $path): void
    {
        Storage::disk('private')->delete($path);
    }
}

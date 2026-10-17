<?php

declare(strict_types=1);

namespace Src\Clinical\Infrastructure;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Src\Clinical\Application\FileVault;
use Src\Shared\Application\PiiProtector;
use Src\Shared\Application\TenantContext;
use Src\Shared\Domain\IdGenerator;

/**
 * Berkas klinis di disk privat (tanpa symlink, tanpa URL publik). Bila `sikg.encrypt_files` aktif, isi dienkripsi di aplikasi
 * sebelum ditulis dan didekripsi saat disajikan; satu dekorator ini dipilih konfigurasi (profil hosting bersama).
 */
final readonly class PrivateFileVault implements FileVault
{
    public function __construct(private PiiProtector $crypto, private IdGenerator $ids, private TenantContext $tenant, private bool $encrypt) {}

    public function put(string $patientId, string $contents, string $extension): array
    {
        $clinic = $this->tenant->clinicId() ?? throw new RuntimeException('Konteks klinik kosong.');
        $path = "clinical/{$clinic}/{$patientId}/{$this->ids->generate()}.{$extension}";
        Storage::disk('private')->put($path, $this->encrypt ? $this->crypto->encrypt(base64_encode($contents)) : $contents);

        return ['path' => $path, 'encrypted' => $this->encrypt];
    }

    public function get(string $path, bool $encrypted): string
    {
        $stored = Storage::disk('private')->get($path);
        if ($stored === null) {
            throw new RuntimeException('Berkas klinis tidak ditemukan di penyimpanan.');
        }

        return $encrypted ? (string) base64_decode($this->crypto->decrypt($stored), true) : $stored;
    }
}

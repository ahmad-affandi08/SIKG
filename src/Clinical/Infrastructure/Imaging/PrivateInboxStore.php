<?php

declare(strict_types=1);

namespace Src\Clinical\Infrastructure\Imaging;

use DateTimeImmutable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Src\Clinical\Application\Imaging\ImagingService;
use Src\Clinical\Application\Imaging\InboxStore;
use Src\Clinical\Domain\InvalidClinicalData;
use Src\Shared\Application\TenantContext;

/**
 * Folder masuk di disk privat: `imaging-inbox/{klinik}/`. Nama berkas dibatasi pola aman (tanpa pemisah folder) agar tidak dapat
 * keluar dari folder; berkas yang selesai dipindah ke subfolder, tidak dihapus.
 */
final readonly class PrivateInboxStore implements InboxStore
{
    private const NAME = '/^[A-Za-z0-9][A-Za-z0-9._ -]{0,100}$/';

    public function __construct(private TenantContext $tenant) {}

    public function pending(): array
    {
        $disk = Storage::disk('private');
        $list = [];
        foreach ($disk->files($this->dir()) as $path) {
            $name = basename($path);
            if (! $this->allowed($name)) {
                continue;
            }
            $list[] = ['name' => $name, 'size' => (int) $disk->size($path), 'modified' => (new DateTimeImmutable)->setTimestamp((int) $disk->lastModified($path))];
        }
        usort($list, fn (array $a, array $b) => $b['modified'] <=> $a['modified'] ?: strcmp($a['name'], $b['name']));

        return $list;
    }

    public function read(string $name): string
    {
        $path = $this->path($name);
        $contents = Storage::disk('private')->exists($path) ? Storage::disk('private')->get($path) : null;
        if ($contents === null) {
            throw new InvalidClinicalData('clinical.inbox_missing', 'Berkas tidak ada lagi di folder masuk.');
        }

        return $contents;
    }

    public function archive(string $name, string $folder): void
    {
        $from = $this->path($name);
        $to = $this->dir().'/'.$folder.'/'.date('YmdHis').'-'.$name;
        if (! Storage::disk('private')->move($from, $to)) {
            throw new RuntimeException('Berkas tidak dapat dipindahkan.');
        }
    }

    private function dir(): string
    {
        return 'imaging-inbox/'.($this->tenant->clinicId() ?? throw new RuntimeException('Konteks klinik kosong.'));
    }

    private function path(string $name): string
    {
        if (! $this->allowed($name)) {
            throw new InvalidClinicalData('clinical.inbox_name', 'Nama berkas tidak valid.');
        }

        return $this->dir().'/'.$name;
    }

    private function allowed(string $name): bool
    {
        return preg_match(self::NAME, $name) === 1 && ! str_contains($name, '..') && in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), ImagingService::INBOX_EXTENSIONS, true);
    }
}

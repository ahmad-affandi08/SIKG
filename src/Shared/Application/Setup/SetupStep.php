<?php

declare(strict_types=1);

namespace Src\Shared\Application\Setup;

/**
 * Satu langkah daftar periksa Penyiapan awal (MST-10). `order` menentukan urutan tampil lintas konteks.
 */
final readonly class SetupStep
{
    public function __construct(
        public int $order,
        public string $key,
        public string $title,
        public string $detail,
        public bool $done,
        public bool $required,
        public string $summary,
        public string $href,
        public string $cta,
        public ?string $importHref = null,
    ) {}

    /**
     * @return array{key: string, title: string, detail: string, done: bool, required: bool, summary: string, href: string, cta: string, import_href: string|null}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key, 'title' => $this->title, 'detail' => $this->detail, 'done' => $this->done, 'required' => $this->required,
            'summary' => $this->summary, 'href' => $this->href, 'cta' => $this->cta, 'import_href' => $this->importHref,
        ];
    }
}

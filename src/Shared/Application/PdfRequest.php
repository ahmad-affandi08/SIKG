<?php

declare(strict_types=1);

namespace Src\Shared\Application;

final readonly class PdfRequest
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array{0: float, 1: float}|null  $customSizePt  lebar dan tinggi dalam poin (struk); kosong berarti A4
     */
    public function __construct(
        public string $view,
        public array $data,
        public string $title,
        public bool $landscape = false,
        public ?array $customSizePt = null,
        public bool $pageNumbers = true,
    ) {}
}

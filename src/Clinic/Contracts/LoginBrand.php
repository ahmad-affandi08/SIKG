<?php

declare(strict_types=1);

namespace Src\Clinic\Contracts;

final readonly class LoginBrand
{
    /** @param string|null $logoVersion potongan nama berkas logo; berubah saat logo diganti agar cache peramban diperbarui */
    public function __construct(public string $name, public ?string $logoVersion) {}
}

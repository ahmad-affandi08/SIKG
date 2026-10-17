<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

enum FileKind: string
{
    case Xray = 'xray';
    case Photo = 'photo';
    case Document = 'document';

    public function label(): string
    {
        return match ($this) {
            self::Xray => 'Rontgen',
            self::Photo => 'Foto klinis',
            self::Document => 'Dokumen',
        };
    }
}

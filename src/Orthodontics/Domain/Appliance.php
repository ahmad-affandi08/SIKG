<?php

declare(strict_types=1);

namespace Src\Orthodontics\Domain;

enum Appliance: string
{
    case MetalBraces = 'metal_braces';
    case CeramicBraces = 'ceramic_braces';
    case Aligner = 'aligner';
    case Retainer = 'retainer';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::MetalBraces => 'Behel logam',
            self::CeramicBraces => 'Behel keramik',
            self::Aligner => 'Aligner bening',
            self::Retainer => 'Retainer',
            self::Other => 'Lainnya',
        };
    }

    /** Kolom kontrol berisi nomor tray untuk aligner dan kawat untuk behel. */
    public function controlLabel(): string
    {
        return $this === self::Aligner ? 'Tray aligner' : 'Kawat atau komponen';
    }
}

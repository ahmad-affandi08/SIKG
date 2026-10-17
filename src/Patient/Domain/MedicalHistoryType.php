<?php

declare(strict_types=1);

namespace Src\Patient\Domain;

enum MedicalHistoryType: string
{
    case Allergy = 'allergy';
    case SystemicDisease = 'systemic_disease';
    case RegularMedication = 'regular_medication';
    case Pregnancy = 'pregnancy';

    public function label(): string
    {
        return match ($this) {
            self::Allergy => 'Alergi',
            self::SystemicDisease => 'Penyakit sistemik',
            self::RegularMedication => 'Obat rutin',
            self::Pregnancy => 'Kehamilan',
        };
    }
}

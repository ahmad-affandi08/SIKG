<?php

declare(strict_types=1);

namespace Src\Integration\Domain;

/**
 * Lingkungan platform SATUSEHAT. Klinik menguji di staging lebih dulu sebelum memakai produksi.
 */
enum SatuSehatEnvironment: string
{
    case Staging = 'staging';
    case Production = 'production';

    public function label(): string
    {
        return match ($this) {
            self::Staging => 'Staging (uji coba)',
            self::Production => 'Produksi',
        };
    }

    public function authUrl(): string
    {
        return match ($this) {
            self::Staging => 'https://api-satusehat-stg.dto.kemkes.go.id/oauth2/v1',
            self::Production => 'https://api-satusehat.kemkes.go.id/oauth2/v1',
        };
    }

    public function fhirUrl(): string
    {
        return match ($this) {
            self::Staging => 'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1',
            self::Production => 'https://api-satusehat.kemkes.go.id/fhir-r4/v1',
        };
    }
}

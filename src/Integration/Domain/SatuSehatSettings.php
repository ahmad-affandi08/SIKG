<?php

declare(strict_types=1);

namespace Src\Integration\Domain;

/**
 * Pengaturan koneksi SATUSEHAT satu klinik. `clientSecret` berisi nilai terdekripsi dan hanya hidup di memori selama
 * pemanggilan API; tidak boleh ditulis ke log atau dikirim ke layar.
 */
final readonly class SatuSehatSettings
{
    public function __construct(
        public bool $enabled,
        public SatuSehatEnvironment $environment,
        public ?string $organizationId,
        public ?string $locationId,
        public ?string $locationName,
        public ?string $clientId,
        public ?string $clientSecret,
    ) {}

    public function isComplete(): bool
    {
        return $this->organizationId !== null && $this->locationId !== null && $this->clientId !== null && $this->clientSecret !== null;
    }

    public static function empty(): self
    {
        return new self(false, SatuSehatEnvironment::Staging, null, null, null, null, null);
    }
}

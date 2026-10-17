<?php

declare(strict_types=1);

namespace Src\Integration\Domain;

interface SatuSehatSettingsRepository
{
    public function get(): SatuSehatSettings;

    /** Menyimpan pengaturan; `$clientSecret` null berarti rahasia lama dipertahankan. */
    public function save(string $actorId, bool $enabled, SatuSehatEnvironment $environment, ?string $organizationId, ?string $locationId, ?string $locationName, ?string $clientId, ?string $clientSecret): void;

    public function hasSecret(): bool;
}

<?php

declare(strict_types=1);

namespace Src\Integration\Infrastructure;

use Src\Integration\Domain\SatuSehatEnvironment;
use Src\Integration\Domain\SatuSehatSettings;
use Src\Integration\Domain\SatuSehatSettingsRepository;
use Src\Integration\Infrastructure\Eloquent\SatuSehatSettingsModel;
use Src\Shared\Application\PiiProtector;

final readonly class EloquentSatuSehatSettingsRepository implements SatuSehatSettingsRepository
{
    public function __construct(private PiiProtector $pii) {}

    public function get(): SatuSehatSettings
    {
        $row = SatuSehatSettingsModel::query()->first();
        if ($row === null) {
            return SatuSehatSettings::empty();
        }

        return new SatuSehatSettings(
            $row->enabled,
            SatuSehatEnvironment::tryFrom($row->environment) ?? SatuSehatEnvironment::Staging,
            $row->organization_id,
            $row->location_id,
            $row->location_name,
            $row->client_id,
            $row->client_secret_encrypted === null ? null : $this->pii->decrypt($row->client_secret_encrypted),
        );
    }

    public function save(string $actorId, bool $enabled, SatuSehatEnvironment $environment, ?string $organizationId, ?string $locationId, ?string $locationName, ?string $clientId, ?string $clientSecret): void
    {
        $row = SatuSehatSettingsModel::query()->first() ?? (new SatuSehatSettingsModel)->forceFill(['created_by' => $actorId]);
        $row->forceFill([
            'enabled' => $enabled,
            'environment' => $environment->value,
            'organization_id' => $organizationId,
            'location_id' => $locationId,
            'location_name' => $locationName,
            'client_id' => $clientId,
            'updated_by' => $actorId,
        ]);
        if ($clientSecret !== null) {
            $row->client_secret_encrypted = $this->pii->encrypt($clientSecret);
        }
        $row->save();
    }

    public function hasSecret(): bool
    {
        return SatuSehatSettingsModel::query()->whereNotNull('client_secret_encrypted')->exists();
    }
}

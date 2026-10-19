<?php

declare(strict_types=1);

namespace Src\Integration\Infrastructure;

use Src\Integration\Domain\Reminder\ReminderKind;
use Src\Integration\Domain\Reminder\ReminderSettings;
use Src\Integration\Domain\Reminder\ReminderSettingsRepository;
use Src\Integration\Domain\Reminder\WhatsAppChannel;
use Src\Integration\Domain\Reminder\WhatsAppProvider;
use Src\Integration\Infrastructure\Eloquent\ReminderSettingsModel;
use Src\Shared\Application\PiiProtector;

final readonly class EloquentReminderSettingsRepository implements ReminderSettingsRepository
{
    public function __construct(private PiiProtector $pii) {}

    public function get(): ReminderSettings
    {
        $row = ReminderSettingsModel::query()->first();
        if ($row === null) {
            return ReminderSettings::empty();
        }

        return new ReminderSettings(
            $row->enabled, $row->h1_enabled, $row->h0_enabled, $row->recall_enabled, $row->recall_months,
            [ReminderKind::DayBefore->value => $row->h1_template, ReminderKind::SameDay->value => $row->h0_template, ReminderKind::Recall->value => $row->recall_template],
            [ReminderKind::DayBefore->value => $row->api_template_h1, ReminderKind::SameDay->value => $row->api_template_h0, ReminderKind::Recall->value => $row->api_template_recall],
            new WhatsAppChannel(
                WhatsAppProvider::tryFrom($row->provider) ?? WhatsAppProvider::Link,
                $row->api_phone_number_id,
                $row->api_token_encrypted === null ? null : $this->pii->decrypt($row->api_token_encrypted),
                $row->api_base_url,
                $row->api_session,
                $row->api_language,
                $row->staff_template,
            ),
        );
    }

    public function save(string $actorId, bool $enabled, bool $dayBefore, bool $sameDay, bool $recall, int $recallMonths, array $templates, array $apiTemplates): void
    {
        $row = $this->row($actorId);
        $row->forceFill([
            'enabled' => $enabled, 'h1_enabled' => $dayBefore, 'h0_enabled' => $sameDay, 'recall_enabled' => $recall, 'recall_months' => $recallMonths,
            'h1_template' => $templates[ReminderKind::DayBefore->value] ?? null,
            'h0_template' => $templates[ReminderKind::SameDay->value] ?? null,
            'recall_template' => $templates[ReminderKind::Recall->value] ?? null,
            'api_template_h1' => $apiTemplates[ReminderKind::DayBefore->value] ?? null,
            'api_template_h0' => $apiTemplates[ReminderKind::SameDay->value] ?? null,
            'api_template_recall' => $apiTemplates[ReminderKind::Recall->value] ?? null,
            'updated_by' => $actorId,
        ])->save();
    }

    public function saveChannel(string $actorId, WhatsAppProvider $provider, ?string $phoneNumberId, ?string $token, bool $replaceToken, ?string $baseUrl, ?string $session, string $language, ?string $staffTemplate): void
    {
        $row = $this->row($actorId);
        $row->forceFill([
            'provider' => $provider->value,
            // `mode` lama tetap konsisten: api bagi semua saluran otomatis.
            'mode' => $provider->automatic() ? 'api' : 'link',
            'api_phone_number_id' => $phoneNumberId,
            'api_base_url' => $baseUrl,
            'api_session' => $session,
            'api_language' => $language,
            'staff_template' => $staffTemplate,
            'updated_by' => $actorId,
        ]);
        if ($replaceToken) {
            $row->api_token_encrypted = $token === null ? null : $this->pii->encrypt($token);
        }
        $row->save();
    }

    private function row(string $actorId): ReminderSettingsModel
    {
        return ReminderSettingsModel::query()->first() ?? (new ReminderSettingsModel)->forceFill(['created_by' => $actorId]);
    }

    public function hasToken(): bool
    {
        return ReminderSettingsModel::query()->whereNotNull('api_token_encrypted')->exists();
    }
}

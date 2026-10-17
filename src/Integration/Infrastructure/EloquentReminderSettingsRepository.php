<?php

declare(strict_types=1);

namespace Src\Integration\Infrastructure;

use Src\Integration\Domain\Reminder\ReminderKind;
use Src\Integration\Domain\Reminder\ReminderSettings;
use Src\Integration\Domain\Reminder\ReminderSettingsRepository;
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
            $row->enabled, $row->mode, $row->h1_enabled, $row->h0_enabled, $row->recall_enabled, $row->recall_months,
            [ReminderKind::DayBefore->value => $row->h1_template, ReminderKind::SameDay->value => $row->h0_template, ReminderKind::Recall->value => $row->recall_template],
            $row->api_phone_number_id,
            $row->api_token_encrypted === null ? null : $this->pii->decrypt($row->api_token_encrypted),
            [ReminderKind::DayBefore->value => $row->api_template_h1, ReminderKind::SameDay->value => $row->api_template_h0, ReminderKind::Recall->value => $row->api_template_recall],
            $row->api_language,
        );
    }

    public function save(string $actorId, bool $enabled, string $mode, bool $dayBefore, bool $sameDay, bool $recall, int $recallMonths, array $templates, ?string $apiPhoneNumberId, ?string $apiToken, array $apiTemplates, string $apiLanguage): void
    {
        $row = ReminderSettingsModel::query()->first() ?? (new ReminderSettingsModel)->forceFill(['created_by' => $actorId]);
        $row->forceFill([
            'enabled' => $enabled, 'mode' => $mode, 'h1_enabled' => $dayBefore, 'h0_enabled' => $sameDay, 'recall_enabled' => $recall, 'recall_months' => $recallMonths,
            'h1_template' => $templates[ReminderKind::DayBefore->value] ?? null,
            'h0_template' => $templates[ReminderKind::SameDay->value] ?? null,
            'recall_template' => $templates[ReminderKind::Recall->value] ?? null,
            'api_phone_number_id' => $apiPhoneNumberId,
            'api_template_h1' => $apiTemplates[ReminderKind::DayBefore->value] ?? null,
            'api_template_h0' => $apiTemplates[ReminderKind::SameDay->value] ?? null,
            'api_template_recall' => $apiTemplates[ReminderKind::Recall->value] ?? null,
            'api_language' => $apiLanguage,
            'updated_by' => $actorId,
        ]);
        if ($apiToken !== null) {
            $row->api_token_encrypted = $this->pii->encrypt($apiToken);
        }
        $row->save();
    }

    public function hasToken(): bool
    {
        return ReminderSettingsModel::query()->whereNotNull('api_token_encrypted')->exists();
    }
}

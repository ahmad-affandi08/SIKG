<?php

declare(strict_types=1);

namespace Src\Integration\Application\Reminder;

use Src\Integration\Domain\Reminder\InvalidReminder;
use Src\Integration\Domain\Reminder\ReminderKind;
use Src\Integration\Domain\Reminder\ReminderMessage;
use Src\Integration\Domain\Reminder\ReminderSettings;
use Src\Integration\Domain\Reminder\ReminderSettingsRepository;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Transaction;

/**
 * P2-03: pengaturan pengingat (jenis, templat pesan, mode tautan atau API). Token API hanya masuk dan tidak pernah dikirim balik.
 */
final readonly class ReminderAdministration
{
    public function __construct(private ReminderSettingsRepository $settings, private AuditLogger $audit, private Transaction $tx) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $s = $this->settings->get();
        $kinds = [];
        foreach (ReminderKind::cases() as $kind) {
            $kinds[] = [
                'kind' => $kind->value,
                'label' => $kind->label(),
                'enabled' => $s->kindEnabled($kind),
                'template' => $s->templates[$kind->value] ?? '',
                'default_template' => $kind->defaultTemplate(),
                'api_template' => $s->apiTemplates[$kind->value] ?? '',
                'api_parameters' => $kind->apiParameters(),
            ];
        }

        return [
            'settings' => [
                'enabled' => $s->enabled,
                'mode' => $s->mode,
                'recall_months' => $s->recallMonths,
                'api_phone_number_id' => $s->apiPhoneNumberId,
                'api_language' => $s->apiLanguage,
                'has_token' => $this->settings->hasToken(),
                'api_ready' => $s->apiReady(),
            ],
            'kinds' => $kinds,
            'placeholders' => ReminderMessage::PLACEHOLDERS,
        ];
    }

    /**
     * @param  array<string, array{enabled: bool, template: ?string, api_template: ?string}>  $kinds  kind => isian
     *
     * @throws InvalidReminder
     */
    public function save(string $actorId, bool $enabled, string $mode, int $recallMonths, ?string $apiPhoneNumberId, ?string $apiToken, string $apiLanguage, array $kinds): void
    {
        if (! in_array($mode, [ReminderSettings::MODE_LINK, ReminderSettings::MODE_API], true)) {
            throw new InvalidReminder('Mode pengingat tidak dikenal.');
        }
        if ($recallMonths < 1 || $recallMonths > 24) {
            throw new InvalidReminder('Jarak kontrol berkala harus 1 sampai 24 bulan.');
        }
        $clean = fn (?string $v) => $v === null || trim($v) === '' ? null : trim($v);
        [$apiPhoneNumberId, $apiToken] = [$clean($apiPhoneNumberId), $clean($apiToken)];

        $templates = [];
        $apiTemplates = [];
        foreach (ReminderKind::cases() as $kind) {
            $input = $kinds[$kind->value] ?? ['enabled' => true, 'template' => null, 'api_template' => null];
            $template = $clean($input['template'] ?? null);
            if ($template !== null) {
                $unknown = ReminderMessage::unknownPlaceholders($template);
                if ($unknown !== []) {
                    throw new InvalidReminder('Templat '.$kind->label().' memuat penanda yang tidak dikenal: {'.implode('}, {', $unknown).'}.');
                }
            }
            $templates[$kind->value] = $template;
            $apiTemplates[$kind->value] = $clean($input['api_template'] ?? null);
        }

        if ($enabled && $mode === ReminderSettings::MODE_API && ($apiPhoneNumberId === null || ($apiToken === null && ! $this->settings->hasToken()))) {
            throw new InvalidReminder('Mode API butuh ID nomor telepon dan token WhatsApp Business API.');
        }

        $this->tx->run(function () use ($actorId, $enabled, $mode, $recallMonths, $apiPhoneNumberId, $apiToken, $apiLanguage, $kinds, $templates, $apiTemplates): void {
            $before = $this->settings->get();
            $this->settings->save(
                $actorId, $enabled, $mode,
                (bool) ($kinds['h1']['enabled'] ?? true), (bool) ($kinds['h0']['enabled'] ?? true), (bool) ($kinds['recall']['enabled'] ?? true),
                $recallMonths, $templates, $apiPhoneNumberId, $apiToken, $apiTemplates, trim($apiLanguage) === '' ? 'id' : trim($apiLanguage),
            );
            $this->audit->record(new AuditEntry(
                $actorId, 'reminder.settings_updated', 'reminder_settings', $actorId,
                ['enabled' => $before->enabled, 'mode' => $before->mode],
                ['enabled' => $enabled, 'mode' => $mode, 'recall_months' => $recallMonths, 'token_changed' => $apiToken !== null],
            ));
        });
    }
}

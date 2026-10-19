<?php

declare(strict_types=1);

namespace Src\Integration\Application\Reminder;

use Src\Integration\Domain\Reminder\InvalidReminder;
use Src\Integration\Domain\Reminder\ReminderKind;
use Src\Integration\Domain\Reminder\ReminderMessage;
use Src\Integration\Domain\Reminder\ReminderSettingsRepository;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Transaction;

/**
 * P2-03: pengaturan isi pengingat (jenis, templat pesan, jarak kontrol). Saluran pengiriman (WhatsApp Resmi atau penyedia tidak resmi)
 * diatur di WhatsAppAdministration.
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
                'mode' => $s->mode(),
                'recall_months' => $s->recallMonths,
                'provider' => $s->channel->provider->value,
                'provider_label' => $s->channel->provider->label(),
                'uses_templates' => $s->channel->provider->usesTemplates(),
                'channel_ready' => $s->apiReady(),
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
    public function save(string $actorId, bool $enabled, int $recallMonths, array $kinds): void
    {
        if ($recallMonths < 1 || $recallMonths > 24) {
            throw new InvalidReminder('Jarak kontrol berkala harus 1 sampai 24 bulan.');
        }
        $clean = fn (?string $v) => $v === null || trim($v) === '' ? null : trim($v);

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

        $this->tx->run(function () use ($actorId, $enabled, $recallMonths, $kinds, $templates, $apiTemplates): void {
            $before = $this->settings->get();
            $this->settings->save(
                $actorId, $enabled,
                (bool) ($kinds['h1']['enabled'] ?? true), (bool) ($kinds['h0']['enabled'] ?? true), (bool) ($kinds['recall']['enabled'] ?? true),
                $recallMonths, $templates, $apiTemplates,
            );
            $this->audit->record(new AuditEntry(
                $actorId, 'reminder.settings_updated', 'reminder_settings', $actorId,
                ['enabled' => $before->enabled],
                ['enabled' => $enabled, 'recall_months' => $recallMonths],
            ));
        });
    }
}

<?php

declare(strict_types=1);

namespace Src\Integration\Application\Reminder;

use Src\Integration\Domain\Reminder\ReminderRepository;
use Src\Integration\Domain\Reminder\ReminderSettingsRepository;
use Src\Integration\Domain\Reminder\WhatsAppFailure;
use Src\Patient\Contracts\PatientPhoneLookup;
use Src\Shared\Application\Clock;

/**
 * P2-03: mengirim pengingat yang jatuh tempo lewat WhatsApp Business API, hanya bila mode API aktif dan lengkap. Pengingat yang sudah
 * tidak relevan atau tanpa nomor HP dilewati; kegagalan dicoba lagi pada putaran berikutnya hingga tiga kali. Pesan tidak disimpan.
 */
final readonly class SendReminders
{
    public const MAX_ATTEMPTS = 3;

    public function __construct(
        private ReminderSettingsRepository $settings,
        private ReminderRepository $reminders,
        private PatientPhoneLookup $phones,
        private ReminderContent $content,
        private WhatsAppGateway $gateway,
        private Clock $clock,
    ) {}

    /**
     * @return array{sent: int, failed: int, skipped: int}
     */
    public function run(int $limit = 20): array
    {
        $result = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        $settings = $this->settings->get();
        if (! $settings->enabled || ! $settings->isApi() || ! $settings->apiReady()) {
            return $result;
        }

        $due = $this->reminders->pendingDue($this->clock->now(), $limit);
        $phones = $this->phones->phones(array_values(array_unique(array_map(fn ($r) => $r->patientId, $due))));

        foreach ($due as $reminder) {
            $patient = $phones[$reminder->patientId] ?? null;
            $template = $settings->apiTemplate($reminder->kind);
            if ($patient === null || $patient->phone === null || $this->reminders->isOptedOut($reminder->patientId)) {
                $this->reminders->markSkipped($reminder->id, 'Pasien tidak punya nomor HP atau tidak mau diingatkan.');
                $result['skipped']++;

                continue;
            }
            $content = $this->content->build($reminder, $patient, $settings);
            if ($content === null) {
                $this->reminders->markSkipped($reminder->id, 'Janji sudah tidak aktif.');
                $result['skipped']++;

                continue;
            }
            if ($template === null) {
                $this->reminders->markSkipped($reminder->id, 'Nama templat WhatsApp untuk jenis ini belum diisi.');
                $result['skipped']++;

                continue;
            }

            try {
                $this->gateway->sendTemplate($settings, $patient->phone, $template, $content['parameters']);
                $this->reminders->markSent($reminder->id, 'api', null, $this->clock->now());
                $result['sent']++;
            } catch (WhatsAppFailure $e) {
                $this->reminders->markAttemptFailed($reminder->id, $e->getMessage(), $e->retryable ? self::MAX_ATTEMPTS : 1);
                $result['failed']++;
            }
        }

        return $result;
    }
}

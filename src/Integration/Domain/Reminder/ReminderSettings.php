<?php

declare(strict_types=1);

namespace Src\Integration\Domain\Reminder;

/**
 * Pengaturan pengingat satu klinik. Mode `link`: pengingat disiapkan dan petugas membuka WhatsApp dengan satu klik (gratis).
 * Mode `api`: pengingat dikirim otomatis lewat WhatsApp Business API milik klinik (berbiaya sesuai penyedia). `apiToken`
 * berisi nilai terdekripsi dan tidak boleh ditulis ke log atau layar.
 */
final readonly class ReminderSettings
{
    public const MODE_LINK = 'link';

    public const MODE_API = 'api';

    /**
     * @param  array<string, string|null>  $templates  kind => templat kustom
     * @param  array<string, string|null>  $apiTemplates  kind => nama templat WhatsApp yang disetujui
     */
    public function __construct(
        public bool $enabled,
        public string $mode,
        public bool $dayBefore,
        public bool $sameDay,
        public bool $recall,
        public int $recallMonths,
        public array $templates,
        public ?string $apiPhoneNumberId,
        public ?string $apiToken,
        public array $apiTemplates,
        public string $apiLanguage,
    ) {}

    public static function empty(): self
    {
        return new self(false, self::MODE_LINK, true, true, true, 6, [], null, null, [], 'id');
    }

    public function isApi(): bool
    {
        return $this->mode === self::MODE_API;
    }

    public function apiReady(): bool
    {
        return $this->apiPhoneNumberId !== null && $this->apiToken !== null;
    }

    public function kindEnabled(ReminderKind $kind): bool
    {
        return match ($kind) {
            ReminderKind::DayBefore => $this->dayBefore,
            ReminderKind::SameDay => $this->sameDay,
            ReminderKind::Recall => $this->recall,
        };
    }

    public function template(ReminderKind $kind): string
    {
        $custom = $this->templates[$kind->value] ?? null;

        return $custom !== null && trim($custom) !== '' ? $custom : $kind->defaultTemplate();
    }

    public function apiTemplate(ReminderKind $kind): ?string
    {
        $name = $this->apiTemplates[$kind->value] ?? null;

        return $name !== null && trim($name) !== '' ? $name : null;
    }
}

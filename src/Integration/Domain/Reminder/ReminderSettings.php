<?php

declare(strict_types=1);

namespace Src\Integration\Domain\Reminder;

/**
 * Pengaturan pengingat satu klinik. Saluran `link`: pengingat disiapkan dan petugas membuka WhatsApp dengan satu klik (gratis).
 * Saluran otomatis: dikirim lewat WhatsApp Resmi (Meta, berbasis templat) atau penyedia tidak resmi (teks biasa) sesuai
 * `channel`. Token saluran berisi nilai terdekripsi dan tidak boleh ditulis ke log atau layar.
 */
final readonly class ReminderSettings
{
    public const MODE_LINK = 'link';

    public const MODE_API = 'api';

    /**
     * @param  array<string, string|null>  $templates  kind => templat kustom
     * @param  array<string, string|null>  $apiTemplates  kind => nama templat WhatsApp Resmi (Meta) yang disetujui
     */
    public function __construct(
        public bool $enabled,
        public bool $dayBefore,
        public bool $sameDay,
        public bool $recall,
        public int $recallMonths,
        public array $templates,
        public array $apiTemplates,
        public WhatsAppChannel $channel,
    ) {}

    public static function empty(): self
    {
        return new self(false, true, true, true, 6, [], [], WhatsAppChannel::manual());
    }

    /** Pengiriman otomatis lewat API (`api`) atau tautan manual (`link`). */
    public function mode(): string
    {
        return $this->channel->automatic() ? self::MODE_API : self::MODE_LINK;
    }

    public function isApi(): bool
    {
        return $this->channel->automatic();
    }

    public function apiReady(): bool
    {
        return $this->channel->ready();
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

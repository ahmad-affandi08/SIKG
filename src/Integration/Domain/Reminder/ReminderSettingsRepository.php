<?php

declare(strict_types=1);

namespace Src\Integration\Domain\Reminder;

interface ReminderSettingsRepository
{
    public function get(): ReminderSettings;

    /**
     * Menyimpan isi pengingat (jenis, templat, jarak kontrol). Saluran WhatsApp disimpan terpisah lewat `saveChannel`.
     *
     * @param  array<string, string|null>  $templates
     * @param  array<string, string|null>  $apiTemplates
     */
    public function save(
        string $actorId,
        bool $enabled,
        bool $dayBefore,
        bool $sameDay,
        bool $recall,
        int $recallMonths,
        array $templates,
        array $apiTemplates,
    ): void;

    /**
     * Menyimpan saluran WhatsApp. `$replaceToken` false mempertahankan token lama; true menggantinya dengan `$token` (null = hapus).
     */
    public function saveChannel(
        string $actorId,
        WhatsAppProvider $provider,
        ?string $phoneNumberId,
        ?string $token,
        bool $replaceToken,
        ?string $baseUrl,
        ?string $session,
        string $language,
        ?string $staffTemplate,
    ): void;

    public function hasToken(): bool;
}

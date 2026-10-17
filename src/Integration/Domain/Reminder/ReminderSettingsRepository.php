<?php

declare(strict_types=1);

namespace Src\Integration\Domain\Reminder;

interface ReminderSettingsRepository
{
    public function get(): ReminderSettings;

    /**
     * Menyimpan pengaturan. `$apiToken` null berarti token lama dipertahankan.
     *
     * @param  array<string, string|null>  $templates
     * @param  array<string, string|null>  $apiTemplates
     */
    public function save(
        string $actorId,
        bool $enabled,
        string $mode,
        bool $dayBefore,
        bool $sameDay,
        bool $recall,
        int $recallMonths,
        array $templates,
        ?string $apiPhoneNumberId,
        ?string $apiToken,
        array $apiTemplates,
        string $apiLanguage,
    ): void;

    public function hasToken(): bool;
}

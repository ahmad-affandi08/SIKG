<?php

declare(strict_types=1);

namespace Src\Integration\Domain\Reminder;

/**
 * Pengaturan saluran WhatsApp satu klinik. `token` berisi nilai terdekripsi dan tidak boleh ditulis ke log atau layar.
 */
final readonly class WhatsAppChannel
{
    public function __construct(
        public WhatsAppProvider $provider,
        public ?string $phoneNumberId,
        public ?string $token,
        public ?string $baseUrl,
        public ?string $session,
        public string $language,
        public ?string $staffTemplate,
    ) {}

    public static function manual(): self
    {
        return new self(WhatsAppProvider::Link, null, null, null, null, 'id', null);
    }

    public function automatic(): bool
    {
        return $this->provider->automatic();
    }

    /** Provider otomatis dengan kredensial wajibnya lengkap. */
    public function ready(): bool
    {
        if (! $this->provider->automatic()) {
            return false;
        }
        foreach ($this->provider->requiredFields() as $field) {
            $value = match ($field) {
                'phone_number_id' => $this->phoneNumberId,
                'token' => $this->token,
                'base_url' => $this->baseUrl,
                default => null,
            };
            if ($value === null || $value === '') {
                return false;
            }
        }

        return true;
    }
}

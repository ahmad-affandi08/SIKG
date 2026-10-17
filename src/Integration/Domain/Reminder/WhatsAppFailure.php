<?php

declare(strict_types=1);

namespace Src\Integration\Domain\Reminder;

use RuntimeException;

/**
 * Galat saat berbicara dengan WhatsApp Business API. Pesannya sudah dibersihkan dari nomor, nama, dan token sehingga aman disimpan.
 */
final class WhatsAppFailure extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable)
    {
        parent::__construct($message);
    }
}

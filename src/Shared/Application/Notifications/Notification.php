<?php

declare(strict_types=1);

namespace Src\Shared\Application\Notifications;

/**
 * Satu pemberitahuan untuk staf (SYS-05). Ditujukan ke siapa pun yang memegang `permission`; `dedupeKey` mencegah
 * pemberitahuan yang sama terbit dua kali (mis. stok menipis hari ini). Tanpa PHI: judul dan isi juga dikirim ke email atau
 * Telegram klinik, jadi jangan memuat nama pasien, NIK, atau diagnosa.
 */
final readonly class Notification
{
    public const MAX_TITLE = 150;

    public const MAX_BODY = 500;

    /**
     * @param  non-empty-string  $kind  contoh `appointment.booked`, `stock.low`
     * @param  non-empty-string  $permission  izin pemegang yang melihatnya
     * @param  non-empty-string  $dedupeKey  unik per klinik
     */
    public function __construct(
        public string $kind,
        public string $title,
        public string $body,
        public ?string $link,
        public string $permission,
        public string $dedupeKey,
    ) {}
}

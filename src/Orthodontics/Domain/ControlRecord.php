<?php

declare(strict_types=1);

namespace Src\Orthodontics\Domain;

/**
 * Catatan kontrol bulanan. Hanya bertambah (M6): koreksi ditulis sebagai catatan kontrol berikutnya.
 */
final readonly class ControlRecord
{
    public function __construct(
        public string $id,
        public string $caseId,
        public string $controlDate,
        public ?string $encounterId,
        public ?string $wireOrTray,
        public ?string $adjustments,
        public ?string $notes,
        public ?string $nextControlDate,
        public string $createdBy,
    ) {}
}

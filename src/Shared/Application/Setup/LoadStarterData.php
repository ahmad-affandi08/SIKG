<?php

declare(strict_types=1);

namespace Src\Shared\Application\Setup;

use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Transaction;

/**
 * MST-10: memuat data awal untuk SATU klinik lewat penyumbang tiap konteks (seeder yang sama dengan `db:seed`), dalam satu
 * transaksi bersama catatan auditnya. Aman dijalankan berulang.
 */
final class LoadStarterData
{
    public function __construct(
        private readonly SetupRegistry $registry,
        private readonly Transaction $tx,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array<string, int> kunci penyumbang => jumlah baris ditambahkan
     */
    public function __invoke(string $actorId, string $clinicId): array
    {
        return $this->tx->run(function () use ($actorId, $clinicId): array {
            $result = [];
            foreach ($this->registry->starters() as $contributor) {
                $result[$contributor->starterKey()] = $contributor->seedClinic($clinicId);
            }
            $this->audit->record(new AuditEntry($actorId, 'setup.starter_data_loaded', 'clinic', $clinicId, null, $result));

            return $result;
        });
    }
}

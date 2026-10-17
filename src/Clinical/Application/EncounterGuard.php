<?php

declare(strict_types=1);

namespace Src\Clinical\Application;

use DateTimeImmutable;
use Src\Clinical\Domain\CorrectionWindow;
use Src\Clinical\Domain\EncounterLocked;
use Src\Clinical\Domain\EncounterNotFound;
use Src\Clinical\Domain\EncounterRecord;
use Src\Clinical\Domain\EncounterRepository;
use Src\Shared\Application\Clock;

/**
 * Penjaga kunci catatan klinis (RM-07, M6) dipakai semua use case yang mengubah isi kunjungan.
 */
final readonly class EncounterGuard
{
    public function __construct(private EncounterRepository $encounters, private Clock $clock) {}

    /** Kunjungan terkunci untuk penulisan, atau EncounterNotFound. */
    public function load(string $encounterId): EncounterRecord
    {
        return $this->encounters->findForUpdate($encounterId) ?? throw new EncounterNotFound;
    }

    /** Hanya kunjungan yang masih berjalan menerima data baru. */
    public function assertOpen(EncounterRecord $encounter): void
    {
        if ($encounter->isClosed()) {
            throw new EncounterLocked;
        }
    }

    /**
     * Perbaikan data yang sudah diinput pada `$inputAt`.
     *
     * @param  bool  $mayCorrectClosed  pengguna berwenang memperbaiki kunjungan yang sudah ditutup
     */
    public function assertCorrectable(EncounterRecord $encounter, DateTimeImmutable $inputAt, bool $mayCorrectClosed): void
    {
        if (! CorrectionWindow::allowsEdit($encounter->isClosed(), $inputAt, $this->clock->now(), $mayCorrectClosed)) {
            throw new EncounterLocked($mayCorrectClosed
                ? 'Batas perbaikan 2x24 jam sudah lewat. Perubahan hanya lewat addendum.'
                : 'Kunjungan sudah ditutup. Perubahan hanya lewat addendum oleh dokter.');
        }
    }
}

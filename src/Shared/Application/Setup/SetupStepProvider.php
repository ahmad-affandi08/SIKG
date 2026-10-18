<?php

declare(strict_types=1);

namespace Src\Shared\Application\Setup;

/**
 * Konteks menyumbang langkah Penyiapan awal dari data miliknya sendiri (tanpa konteks lain membaca tabelnya).
 */
interface SetupStepProvider
{
    /**
     * @return list<SetupStep>
     */
    public function steps(string $clinicId): array;
}

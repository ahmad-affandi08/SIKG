<?php

declare(strict_types=1);

namespace Src\Integration\Application;

use Src\Clinical\Domain\Events\EncounterClosed;
use Src\Integration\Domain\SatuSehatSettingsRepository;
use Src\Integration\Domain\SubmissionRepository;
use Src\Shared\Application\Clock;

/**
 * P2-01: kunjungan yang ditutup masuk antrean pengiriman SATUSEHAT bila integrasi aktif dan lengkap. Pengiriman sendiri
 * dilakukan perintah terjadwal, sehingga menutup kunjungan tidak pernah menunggu jaringan.
 */
final readonly class QueueEncounterSubmission
{
    public function __construct(
        private SatuSehatSettingsRepository $settings,
        private SubmissionRepository $submissions,
        private Clock $clock,
    ) {}

    public function handle(EncounterClosed $event): void
    {
        $settings = $this->settings->get();
        if (! $settings->enabled || ! $settings->isComplete()) {
            return;
        }

        $this->submissions->enqueue($event->encounterId, $event->patientId, $this->clock->now());
    }
}

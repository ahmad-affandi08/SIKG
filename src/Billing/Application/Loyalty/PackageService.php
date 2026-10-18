<?php

declare(strict_types=1);

namespace Src\Billing\Application\Loyalty;

use DateTimeImmutable;
use DateTimeZone;
use Src\Billing\Contracts\EncounterChargeGateway;
use Src\Billing\Domain\InvalidBillingData;
use Src\Billing\Domain\Loyalty\PackageRepository;
use Src\Billing\Domain\Loyalty\PatientPackage;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\Clinical\Contracts\EncounterLookup;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Transaction;
use Src\Shared\Domain\IdGenerator;

/**
 * P3-06: penjualan paket kunjungan. Paket dibeli pasien lewat item tagihan pada kunjungannya yang sedang berjalan (harga disalin,
 * M7) dan aktif sejak dibeli; bila tagihan itu dibatalkan paketnya ikut batal. Pemakaian sesi ada di PromotionService.
 */
final readonly class PackageService
{
    public function __construct(
        private PackageRepository $packages,
        private EncounterLookup $encounters,
        private EncounterChargeGateway $charges,
        private Transaction $tx,
        private AuditLogger $audit,
        private IdGenerator $ids,
        private ClinicCalendar $calendar,
        private Clock $clock,
    ) {}

    public function sell(string $actorId, string $patientId, string $packageId, string $encounterId): string
    {
        return $this->tx->run(function () use ($actorId, $patientId, $packageId, $encounterId): string {
            $catalog = $this->packages->catalog($packageId);
            if ($catalog === null || ! $catalog['active']) {
                throw new InvalidBillingData('package.catalog_not_found', 'Paket tidak ditemukan atau nonaktif.');
            }
            $encounter = $this->encounters->find($encounterId);
            if ($encounter === null || $encounter->patientId !== $patientId || ! $encounter->open) {
                throw new InvalidBillingData('package.encounter_invalid', 'Pilih kunjungan pasien ini yang masih berjalan.');
            }

            $today = $this->clock->now()->setTimezone(new DateTimeZone($this->calendar->timezone()))->format('Y-m-d');
            $expires = $catalog['valid_days'] === null ? null : (new DateTimeImmutable($today))->modify('+'.$catalog['valid_days'].' days')->format('Y-m-d');
            $id = $this->ids->generate();
            $invoiceId = $this->charges->addCharge($encounterId, $patientId, 'package', $id, mb_substr('Paket kunjungan '.$catalog['name'], 0, 255), $catalog['price_amount']);
            $this->packages->add(new PatientPackage($id, $patientId, $catalog['name'], $catalog['sessions'], $catalog['sessions'], $catalog['max_session_value_amount'], $expires, 'active'), $catalog['price_amount'], $packageId, $today, $invoiceId, $actorId);
            $this->audit->record(new AuditEntry($actorId, 'package.sold', 'patient', $patientId, null, ['patient_package_id' => $id, 'name' => $catalog['name'], 'sessions' => $catalog['sessions'], 'price' => $catalog['price_amount'], 'invoice_id' => $invoiceId]));

            return $id;
        });
    }
}

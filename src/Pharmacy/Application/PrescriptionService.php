<?php

declare(strict_types=1);

namespace Src\Pharmacy\Application;

use Src\Clinical\Contracts\EncounterLookup;
use Src\Patient\Contracts\PatientAlerts;
use Src\Pharmacy\Contracts\MedicineCatalog;
use Src\Pharmacy\Contracts\StockLedger;
use Src\Pharmacy\Domain\AllergyMatcher;
use Src\Pharmacy\Domain\AllergyWarning;
use Src\Pharmacy\Domain\Events\MedicineDispensed;
use Src\Pharmacy\Domain\Events\MedicineReturned;
use Src\Pharmacy\Domain\InvalidPharmacyData;
use Src\Pharmacy\Domain\PrescriptionItemInput;
use Src\Pharmacy\Domain\PrescriptionRepository;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Transaction;
use Src\Shared\Domain\DomainEvent;

/**
 * OBT-01, OBT-02, OBT-04: resep dari kunjungan. Obat katalog yang dijual di klinik langsung mengurangi stok dan memberi tahu
 * Billing lewat event (item tagihan dengan salinan harga jual). Peringatan alergi menahan resep sampai dokter mengonfirmasi.
 * Resep hanya dibuat atau dibatalkan selama kunjungan berjalan (RM-07).
 */
final readonly class PrescriptionService
{
    public function __construct(
        private PrescriptionRepository $prescriptions,
        private EncounterLookup $encounters,
        private MedicineCatalog $medicines,
        private PatientAlerts $alerts,
        private StockLedger $stock,
        private Transaction $tx,
        private AuditLogger $audit,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @param  list<PrescriptionItemInput>  $items
     * @param  string|null  $allergyAcknowledgement  alasan dokter tetap meresepkan walau ada peringatan alergi
     * @param  string|null  $stockOverrideReason  hanya diisi bila pengguna berhak override stok (Admin)
     * @return string id resep
     */
    public function create(string $actorId, string $encounterId, array $items, ?string $allergyAcknowledgement = null, ?string $stockOverrideReason = null): string
    {
        if ($items === []) {
            throw new InvalidPharmacyData('prescription.empty', 'Resep minimal berisi satu obat.');
        }
        if (count($items) > 30) {
            throw new InvalidPharmacyData('prescription.too_many_items', 'Resep maksimal 30 baris obat.');
        }

        [$id, $events] = $this->tx->run(function () use ($actorId, $encounterId, $items, $allergyAcknowledgement, $stockOverrideReason): array {
            $encounter = $this->encounters->find($encounterId) ?? throw new InvalidPharmacyData('prescription.encounter_not_found', 'Kunjungan tidak ditemukan.');
            if (! $encounter->open) {
                throw new InvalidPharmacyData('prescription.encounter_closed', 'Kunjungan sudah ditutup; resep tidak dapat ditambah.');
            }

            $resolved = [];
            $allergyMatches = [];
            $allergies = array_values(array_filter($this->alerts->warnings($encounter->patientId), fn ($w) => $w->type === 'allergy'));
            foreach ($items as $item) {
                $name = $item->freeTextName;
                $price = 0;
                if ($item->medicineId !== null) {
                    $medicine = $this->medicines->find($item->medicineId);
                    if ($medicine === null || ! $medicine->active) {
                        throw new InvalidPharmacyData('prescription.medicine_not_found', 'Obat tidak ditemukan atau sudah nonaktif.');
                    }
                    $name = $medicine->name;
                    $price = $item->dispenseInClinic ? $medicine->sellPriceAmount : 0;
                    foreach ($allergies as $allergy) {
                        if (AllergyMatcher::matches($medicine->drugClass, $allergy->name)) {
                            $allergyMatches[] = "{$medicine->name} ({$medicine->drugClass}) - alergi {$allergy->name}";
                        }
                    }
                }
                $resolved[] = [$item, (string) $name, $price];
            }

            $acknowledgement = $allergyAcknowledgement === null ? null : trim($allergyAcknowledgement);
            if ($allergyMatches !== [] && ($acknowledgement === null || $acknowledgement === '')) {
                throw new AllergyWarning(array_values(array_unique($allergyMatches)));
            }

            $now = $this->clock->now();
            $id = $this->prescriptions->add($actorId, $encounterId, $encounter->patientId, $encounter->doctorId, $now, $allergyMatches === [] ? null : mb_substr((string) $acknowledgement, 0, 500));
            $events = [];
            foreach ($resolved as [$item, $name, $price]) {
                $itemId = $this->prescriptions->addItem($actorId, $id, $item, $name, $price, $item->dispenseInClinic ? $now : null);
                if ($item->dispenseInClinic && $item->medicineId !== null) {
                    $this->stock->issue($actorId, $item->medicineId, $item->quantity, 'prescription_item', $itemId, $stockOverrideReason);
                    $events[] = new MedicineDispensed($encounterId, $itemId, $encounter->patientId, $name, $item->quantity, $price, $now);
                }
            }

            $this->audit->record(new AuditEntry($actorId, 'prescription.created', 'encounter', $encounterId, null, [
                'prescription_id' => $id,
                'items' => count($resolved),
                'dispensed' => count($events),
                'allergy_warning_acknowledged' => $allergyMatches !== [],
            ]));

            return [$id, $events];
        });

        $this->events->dispatch(...$events);

        return $id;
    }

    /**
     * Membatalkan resep pada kunjungan yang masih berjalan; obat yang sudah dijual dikembalikan ke stok dan item tagihannya dilepas.
     */
    public function void(string $actorId, string $prescriptionId, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidPharmacyData('prescription.reason_required', 'Alasan pembatalan resep wajib diisi.');
        }

        /** @var list<DomainEvent> $events */
        $events = $this->tx->run(function () use ($actorId, $prescriptionId, $reason): array {
            $prescription = $this->prescriptions->findForUpdate($prescriptionId) ?? throw new InvalidPharmacyData('prescription.not_found', 'Resep tidak ditemukan.');
            if ($prescription->isVoid()) {
                throw new InvalidPharmacyData('prescription.already_void', 'Resep sudah dibatalkan.');
            }
            $encounter = $this->encounters->find($prescription->encounterId);
            if ($encounter === null || ! $encounter->open) {
                throw new InvalidPharmacyData('prescription.encounter_closed', 'Kunjungan sudah ditutup; resep tidak dapat dibatalkan.');
            }

            $now = $this->clock->now();
            $events = [];
            foreach ($prescription->items as $item) {
                if ($item->dispenseInClinic && $item->medicineId !== null) {
                    $this->stock->restore($actorId, $item->medicineId, $item->quantity, 'prescription_item', $item->id, 'Resep dibatalkan: '.$reason);
                    $events[] = new MedicineReturned($prescription->encounterId, $item->id, $now);
                }
            }
            $this->prescriptions->void($actorId, $prescriptionId, mb_substr($reason, 0, 255));
            $this->audit->record(new AuditEntry($actorId, 'prescription.voided', 'encounter', $prescription->encounterId, ['prescription_id' => $prescriptionId], ['reason' => $reason]));

            return $events;
        });

        $this->events->dispatch(...$events);
    }
}

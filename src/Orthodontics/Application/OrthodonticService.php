<?php

declare(strict_types=1);

namespace Src\Orthodontics\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Billing\Contracts\EncounterChargeGateway;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\Clinic\Contracts\DoctorDirectory;
use Src\Clinical\Contracts\EncounterLookup;
use Src\Orthodontics\Domain\Appliance;
use Src\Orthodontics\Domain\InstallmentRecord;
use Src\Orthodontics\Domain\InstallmentSchedule;
use Src\Orthodontics\Domain\InvalidOrthodontics;
use Src\Orthodontics\Domain\OrthoCase;
use Src\Orthodontics\Domain\OrthoRepository;
use Src\Patient\Contracts\PatientLookup;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Transaction;
use Src\Shared\Domain\IdGenerator;

/**
 * P2-04: paket perawatan ortodonti, kontrol bulanan, dan cicilan terjadwal. Cicilan ditagihkan sebagai item pada tagihan
 * kunjungan lewat Billing (pembayarannya tercatat di sana, M7). Catatan kontrol hanya bertambah (M6). Setiap langkah tercatat
 * di audit (M10). Izin dicek di Presentation (M9).
 */
final readonly class OrthodonticService
{
    public function __construct(
        private OrthoRepository $cases,
        private PatientLookup $patients,
        private DoctorDirectory $doctors,
        private EncounterLookup $encounters,
        private EncounterChargeGateway $charges,
        private Transaction $tx,
        private AuditLogger $audit,
        private IdGenerator $ids,
        private ClinicCalendar $calendar,
        private Clock $clock,
    ) {}

    /**
     * @throws InvalidOrthodontics
     */
    public function createCase(string $actorId, string $patientId, string $doctorId, string $appliance, string $title, ?string $planNotes, int $totalFee, string $startDate, int $expectedMonths, int $installmentCount, int $downPayment): string
    {
        $this->patients->exists($patientId) || throw new InvalidOrthodontics('ortho.not_found', 'Pasien tidak ditemukan.');
        $doctor = $this->doctors->find($doctorId);
        if ($doctor === null || ! $doctor->active) {
            throw new InvalidOrthodontics('ortho.doctor_invalid', 'Dokter tidak ditemukan atau nonaktif.');
        }
        $type = Appliance::tryFrom($appliance) ?? throw new InvalidOrthodontics('ortho.appliance_invalid', 'Jenis alat tidak dikenal.');
        $first = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate, new DateTimeZone('UTC'));
        if ($first === false || $first->format('Y-m-d') !== $startDate) {
            throw new InvalidOrthodontics('ortho.date_invalid', 'Tanggal mulai tidak valid.');
        }
        $schedule = InstallmentSchedule::generate($totalFee, $installmentCount, $downPayment, $first);

        return $this->tx->run(function () use ($actorId, $patientId, $doctorId, $type, $title, $planNotes, $totalFee, $startDate, $expectedMonths, $schedule): string {
            $case = OrthoCase::open($this->ids->generate(), $patientId, $doctorId, $type, $title, $planNotes, $totalFee, $startDate, $expectedMonths);
            $this->cases->addCase($case, $schedule, $actorId);
            $this->audit->record(new AuditEntry($actorId, 'orthodontic.case_created', 'patient', $patientId, null, [
                'case_id' => $case->id, 'appliance' => $type->value, 'total_fee' => $totalFee, 'installments' => count($schedule),
            ]));

            return $case->id;
        });
    }

    /**
     * @throws InvalidOrthodontics
     */
    public function addControl(string $actorId, string $caseId, string $controlDate, ?string $encounterId, ?string $wireOrTray, ?string $adjustments, ?string $notes, ?string $nextControlDate): string
    {
        $clean = function (?string $text, int $max): ?string {
            $text = $text === null ? null : trim($text);

            return $text === null || $text === '' ? null : mb_substr($text, 0, $max);
        };
        [$wireOrTray, $adjustments, $notes] = [$clean($wireOrTray, 100), $clean($adjustments, 1000), $clean($notes, 1000)];
        if ($wireOrTray === null && $adjustments === null && $notes === null) {
            throw new InvalidOrthodontics('ortho.control_empty', 'Isi sedikitnya satu dari kawat atau tray, penyesuaian, atau catatan.');
        }
        if ($controlDate > $this->today()) {
            throw new InvalidOrthodontics('ortho.date_invalid', 'Tanggal kontrol tidak boleh di masa depan.');
        }
        if ($nextControlDate !== null && $nextControlDate < $controlDate) {
            throw new InvalidOrthodontics('ortho.date_invalid', 'Tanggal kontrol berikutnya tidak boleh sebelum tanggal kontrol ini.');
        }

        return $this->tx->run(function () use ($actorId, $caseId, $controlDate, $encounterId, $wireOrTray, $adjustments, $notes, $nextControlDate): string {
            $case = $this->cases->lock($caseId) ?? throw new InvalidOrthodontics('ortho.not_found', 'Paket perawatan tidak ditemukan.');
            $case->assertActive();
            if ($encounterId !== null) {
                $encounter = $this->encounters->find($encounterId);
                if ($encounter === null || $encounter->patientId !== $case->patientId) {
                    throw new InvalidOrthodontics('ortho.encounter_invalid', 'Kunjungan tidak ditemukan atau bukan milik pasien ini.');
                }
            }
            $id = $this->cases->addControl($actorId, $caseId, $controlDate, $encounterId, $wireOrTray, $adjustments, $notes, $nextControlDate);
            $this->audit->record(new AuditEntry($actorId, 'orthodontic.control_added', 'patient', $case->patientId, null, ['case_id' => $caseId, 'control_id' => $id, 'control_date' => $controlDate]));

            return $id;
        });
    }

    public function complete(string $actorId, string $caseId, ?string $note): void
    {
        $this->end($actorId, $caseId, 'orthodontic.case_completed', fn (OrthoCase $case) => $case->complete($this->clock->now(), $note), false);
    }

    public function stop(string $actorId, string $caseId, string $reason): void
    {
        $this->end($actorId, $caseId, 'orthodontic.case_stopped', fn (OrthoCase $case) => $case->stop($this->clock->now(), $reason), true);
    }

    /**
     * Menagihkan satu cicilan sebagai item pada tagihan kunjungan yang sedang berjalan.
     *
     * @throws InvalidOrthodontics
     */
    public function chargeInstallment(string $actorId, string $installmentId, string $encounterId): void
    {
        $this->tx->run(function () use ($actorId, $installmentId, $encounterId): void {
            $installment = $this->cases->lockInstallment($installmentId) ?? throw new InvalidOrthodontics('ortho.not_found', 'Cicilan tidak ditemukan.');
            if ($installment->status !== InstallmentRecord::SCHEDULED) {
                throw new InvalidOrthodontics('ortho.installment_not_open', 'Cicilan ini sudah ditagihkan atau dibatalkan.');
            }
            $case = $this->cases->find($installment->caseId) ?? throw new InvalidOrthodontics('ortho.not_found', 'Paket perawatan tidak ditemukan.');
            $encounter = $this->encounters->find($encounterId);
            if ($encounter === null || $encounter->patientId !== $case->patientId || ! $encounter->open) {
                throw new InvalidOrthodontics('ortho.encounter_invalid', 'Pilih kunjungan pasien ini yang masih berjalan.');
            }

            $count = count($this->cases->installments($case->id));
            $invoiceId = $this->charges->addCharge($encounterId, $case->patientId, 'ortho', $installment->id, mb_substr("Cicilan ortodonti {$installment->seq}/{$count} - {$case->title}", 0, 255), $installment->amount);
            $this->cases->markInvoiced($installment->id, $encounterId, $invoiceId, $this->clock->now());
            $this->audit->record(new AuditEntry($actorId, 'orthodontic.installment_charged', 'patient', $case->patientId, null, [
                'case_id' => $case->id, 'installment_id' => $installment->id, 'seq' => $installment->seq, 'amount' => $installment->amount, 'invoice_id' => $invoiceId,
            ]));
        });
    }

    /**
     * @param  non-empty-string  $action
     * @param  callable(OrthoCase): void  $transition
     */
    private function end(string $actorId, string $caseId, string $action, callable $transition, bool $cancelOpen): void
    {
        $this->tx->run(function () use ($actorId, $caseId, $action, $transition, $cancelOpen): void {
            $case = $this->cases->lock($caseId) ?? throw new InvalidOrthodontics('ortho.not_found', 'Paket perawatan tidak ditemukan.');
            $transition($case);
            $cancelled = $cancelOpen ? $this->cases->cancelOpenInstallments($caseId) : 0;
            $this->cases->save($case, $actorId);
            $this->audit->record(new AuditEntry($actorId, $action, 'patient', $case->patientId, null, ['case_id' => $caseId, 'status' => $case->status()->value, 'installments_cancelled' => $cancelled]));
        });
    }

    private function today(): string
    {
        return $this->clock->now()->setTimezone(new DateTimeZone($this->calendar->timezone()))->format('Y-m-d');
    }
}

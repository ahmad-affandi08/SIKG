<?php

declare(strict_types=1);

namespace Src\Scheduling\Application\Booking;

use Src\Clinic\Contracts\DoctorDirectory;
use Src\Patient\Contracts\PatientLookup;
use Src\Patient\Contracts\PatientPhoneFinder;
use Src\Scheduling\Application\AppointmentScheduler;
use Src\Scheduling\Application\ClinicTime;
use Src\Scheduling\Domain\Booking\BookingRepository;
use Src\Scheduling\Domain\Booking\BookingStatus;
use Src\Scheduling\Domain\Booking\InvalidBooking;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Transaction;

/**
 * P3-01: meja staf untuk permintaan booking online. Staf melihat permintaan, mengaitkannya ke pasien terdaftar (kandidat dicari lewat
 * nomor HP), lalu mengonfirmasi menjadi janji biasa atau menolaknya dengan alasan. Nomor HP penuh hanya dibuka atas permintaan dan
 * pembukaannya tercatat di audit (M10, M11).
 */
final readonly class BookingDesk
{
    public function __construct(
        private BookingRepository $bookings,
        private DoctorDirectory $doctors,
        private PatientLookup $patients,
        private PatientPhoneFinder $phones,
        private AppointmentScheduler $scheduler,
        private ClinicTime $time,
        private AuditLogger $audit,
        private Transaction $tx,
        private Clock $clock,
    ) {}

    /**
     * @return array{requests: list<array<string, mixed>>, public_path: string|null}
     */
    public function overview(): array
    {
        $rows = $this->bookings->listForDesk(150);
        $doctors = $this->doctors->byIds(array_values(array_unique(array_map(fn (array $r) => (string) $r['doctor_id'], $rows))));
        $requests = [];
        foreach ($rows as $r) {
            $start = $this->time->toLocal($r['starts_at']);
            $matches = [];
            if ($r['status'] === BookingStatus::Pending->value) {
                $phone = $this->bookings->phoneOf((string) $r['id']);
                foreach ($phone === null ? [] : $this->phones->findByPhone($phone) as $p) {
                    $matches[] = ['id' => $p->id, 'medical_record_no' => $p->medicalRecordNo, 'name' => $p->name];
                }
            }
            $requests[] = [
                'id' => $r['id'], 'status' => $r['status'], 'status_label' => $r['status_label'], 'patient_name' => $r['patient_name'], 'phone_masked' => $r['phone_masked'],
                'doctor' => $doctors[(string) $r['doctor_id']]->name ?? '-', 'date' => $start->format('Y-m-d'), 'time' => $start->format('H:i'),
                'minutes' => (int) (($r['ends_at']->getTimestamp() - $r['starts_at']->getTimestamp()) / 60), 'complaint' => $r['complaint'], 'verified' => $r['verified'],
                'appointment_id' => $r['appointment_id'], 'decision_note' => $r['decision_note'], 'matches' => $matches,
                'requested_at' => $this->time->toLocal($r['created_at'])->format('Y-m-d H:i'),
            ];
        }
        $slug = $this->bookings->settings()->slug;

        return ['requests' => $requests, 'public_path' => $slug === '' ? null : '/book/'.$slug];
    }

    public function revealPhone(string $actorId, string $id): string
    {
        $phone = $this->bookings->phoneOf($id) ?? throw new InvalidBooking('booking.not_found', 'Permintaan booking tidak ditemukan.');
        $this->audit->record(new AuditEntry($actorId, 'booking.phone_viewed', 'booking_request', $id, null, null));

        return $phone;
    }

    /**
     * Mengonfirmasi permintaan menjadi janji untuk pasien terdaftar. Permintaan yang nomornya belum diverifikasi (tanpa WhatsApp API)
     * hanya boleh dikonfirmasi setelah staf menghubungi pasien (`$contacted`).
     */
    public function confirm(string $actorId, string $id, string $patientId, bool $contacted): string
    {
        return $this->tx->run(function () use ($actorId, $id, $patientId, $contacted): string {
            $record = $this->bookings->lockForUpdate($id) ?? throw new InvalidBooking('booking.not_found', 'Permintaan booking tidak ditemukan.');
            if ($record->status !== BookingStatus::Pending) {
                throw new InvalidBooking('booking.not_pending', 'Hanya permintaan yang menunggu konfirmasi yang dapat dikonfirmasi.');
            }
            if (! $record->verified && ! $contacted) {
                throw new InvalidBooking('booking.not_verified', 'Nomor belum diverifikasi. Hubungi pasien lebih dulu, lalu centang bahwa pasien sudah dihubungi.');
            }
            if (! $this->patients->exists($patientId)) {
                throw new InvalidBooking('booking.patient_not_found', 'Pasien tidak ditemukan. Daftarkan pasien lebih dulu.');
            }
            $start = $this->time->toLocal($record->startsAt);
            $minutes = (int) (($record->endsAt->getTimestamp() - $record->startsAt->getTimestamp()) / 60);
            $appointmentId = $this->scheduler->book($actorId, $patientId, $record->doctorId, null, $start->format('Y-m-d'), $start->format('H:i'), $minutes, $record->complaint);
            $this->bookings->transition($id, BookingStatus::Confirmed, $actorId, $contacted && ! $record->verified ? 'Dikonfirmasi setelah pasien dihubungi.' : null, $patientId, $appointmentId, $this->clock->now());
            $this->audit->record(new AuditEntry($actorId, 'booking.confirmed', 'booking_request', $id, ['status' => $record->status->value], ['status' => 'confirmed', 'appointment_id' => $appointmentId, 'patient_id' => $patientId, 'contacted' => $contacted]));

            return $appointmentId;
        });
    }

    public function reject(string $actorId, string $id, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidBooking('booking.reason_required', 'Alasan penolakan wajib diisi.');
        }
        $this->tx->run(function () use ($actorId, $id, $reason): void {
            $record = $this->bookings->lockForUpdate($id) ?? throw new InvalidBooking('booking.not_found', 'Permintaan booking tidak ditemukan.');
            if (! $record->status->holdsSlot()) {
                throw new InvalidBooking('booking.not_pending', 'Permintaan ini sudah diputuskan.');
            }
            $this->bookings->transition($id, BookingStatus::Rejected, $actorId, mb_substr($reason, 0, 255), null, null, $this->clock->now());
            $this->audit->record(new AuditEntry($actorId, 'booking.rejected', 'booking_request', $id, ['status' => $record->status->value], ['status' => 'rejected', 'reason' => mb_substr($reason, 0, 255)]));
        });
    }

    /** Dipakai perintah terjadwal: permintaan yang melewati masa tahan menjadi kedaluwarsa. */
    public function expireDue(): int
    {
        return $this->bookings->expireDue($this->clock->now());
    }
}

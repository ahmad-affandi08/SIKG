<?php

declare(strict_types=1);

namespace Src\Scheduling\Application\Booking;

use DateInterval;
use DateTimeImmutable;
use Src\Clinic\Contracts\DoctorDirectory;
use Src\Clinic\Contracts\LetterheadProvider;
use Src\Patient\Contracts\PatientPhoneFinder;
use Src\Scheduling\Application\ClinicTime;
use Src\Scheduling\Application\ScheduleQueries;
use Src\Scheduling\Contracts\BookingCodeNotSent;
use Src\Scheduling\Contracts\BookingCodeSender;
use Src\Scheduling\Domain\Booking\BookingRecord;
use Src\Scheduling\Domain\Booking\BookingRepository;
use Src\Scheduling\Domain\Booking\BookingSettings;
use Src\Scheduling\Domain\Booking\BookingStatus;
use Src\Scheduling\Domain\Booking\InvalidBooking;
use Src\Scheduling\Domain\InvalidAppointment;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Notifications\Notification;
use Src\Shared\Application\Notifications\StaffAlerts;
use Src\Shared\Application\PiiProtector;
use Src\Shared\Application\ResourceLock;
use Src\Shared\Application\Transaction;
use Src\Shared\Domain\IdGenerator;

/**
 * P3-01: booking online oleh pasien dari halaman publik, tanpa login. Pasien memilih dokter dan slot kosong, mengisi nama dan nomor HP,
 * lalu (bila klinik menyiapkan WhatsApp API) memverifikasi nomor dengan kode. Permintaan menahan slot sampai staf mengonfirmasi atau
 * masa tahan habis. Tidak ada data pasien yang dibuat di sini: staf mengaitkan permintaan ke pasien terdaftar saat mengonfirmasi.
 * Batas janji aktif per nomor dan batas pengiriman kode menahan penyalahgunaan; alamat status memakai token acak (hanya hash tersimpan).
 */
final readonly class PublicBooking
{
    public const CODE_TTL_MINUTES = 10;

    public const MAX_CODE_ATTEMPTS = 5;

    public const MAX_CODES_PER_REQUEST = 3;

    public const MAX_CODES_PER_PHONE_DAY = 5;

    public function __construct(
        private BookingRepository $bookings,
        private DoctorDirectory $doctors,
        private LetterheadProvider $letterhead,
        private PatientPhoneFinder $phones,
        private ScheduleQueries $schedule,
        private ClinicTime $time,
        private BookingCodeSender $sender,
        private PiiProtector $pii,
        private ResourceLock $locks,
        private StaffAlerts $alerts,
        private AuditLogger $audit,
        private Transaction $tx,
        private IdGenerator $ids,
        private Clock $clock,
    ) {}

    /**
     * @return array{clinic: array{name: string, address: string|null}, welcome: string|null, slot_minutes: int, today: string, last_date: string, doctors: list<array{id: string, name: string}>, verification: bool}
     */
    public function portal(): array
    {
        $settings = $this->bookings->settings();
        $head = $this->letterhead->forDocument();
        $today = $this->time->today();

        return [
            'clinic' => ['name' => $head->clinicName, 'address' => $head->address],
            'welcome' => $settings->welcomeText,
            'slot_minutes' => $settings->slotMinutes,
            'today' => $today,
            'last_date' => (new DateTimeImmutable($today))->add(new DateInterval('P'.$settings->horizonDays.'D'))->format('Y-m-d'),
            'doctors' => array_map(fn ($d) => ['id' => $d->id, 'name' => $d->name], $this->doctors->active()),
            'verification' => $this->verificationAvailable($settings),
        ];
    }

    /**
     * Slot kosong dokter pada tanggal itu: bukan janji, bukan permintaan yang sedang menahan, dan sesudah jeda minimum.
     *
     * @return list<string> HH:MM lokal klinik
     */
    public function slots(string $doctorId, string $date): array
    {
        $settings = $this->bookings->settings();
        $this->assertDate($date, $settings);
        $doctor = $this->doctors->find($doctorId);
        if ($doctor === null || ! $doctor->active) {
            return [];
        }

        $now = $this->clock->now();
        $earliest = $now->add(new DateInterval('PT'.$settings->minLeadHours.'H'));
        $free = [];
        foreach ($this->schedule->availability($doctorId, $date, $settings->slotMinutes) as $hhmm) {
            $start = $this->time->parse($date, $hhmm);
            $end = $start->add(new DateInterval('PT'.$settings->slotMinutes.'M'));
            if ($start >= $earliest && $this->bookings->holdsBetween($doctorId, $start, $end, $now) === 0) {
                $free[] = $hhmm;
            }
        }

        return $free;
    }

    /**
     * @return array{token: string, next: 'verify'|'status'}
     */
    public function submit(string $name, string $phoneInput, string $doctorId, string $date, string $time, ?string $complaint, ?string $ip): array
    {
        $settings = $this->bookings->settings();
        $name = trim((string) preg_replace('/\s+/', ' ', $name));
        if (mb_strlen($name) < 3 || mb_strlen($name) > 120) {
            throw new InvalidBooking('booking.name_invalid', 'Nama lengkap diisi 3 sampai 120 karakter.');
        }
        $phone = $this->phones->normalize($phoneInput) ?? throw new InvalidBooking('booking.phone_invalid', 'Nomor HP harus nomor Indonesia yang sah (08..., 628..., atau +628...).');
        $phoneHash = $this->pii->blindIndex('phone', $phone);
        $complaint = $complaint === null || trim($complaint) === '' ? null : mb_substr(trim($complaint), 0, 300);

        $sendCode = $this->verificationAvailable($settings);
        $code = $sendCode ? (string) random_int(100000, 999999) : null;
        $token = bin2hex(random_bytes(20));
        $now = $this->clock->now();

        $id = $this->tx->run(function () use ($settings, $name, $phone, $phoneHash, $doctorId, $date, $time, $complaint, $code, $token, $ip, $now): string {
            $this->locks->acquire("doctor:{$doctorId}", "booking-phone:{$phoneHash}");
            $this->assertDate($date, $settings);
            if (! in_array($time, $this->slots($doctorId, $date), true)) {
                throw new InvalidBooking('booking.slot_unavailable', 'Slot itu baru saja terisi atau tidak tersedia. Pilih waktu lain.');
            }
            if ($this->bookings->activeCountForPhone($phoneHash, $now) >= $settings->maxActivePerPhone) {
                throw new InvalidBooking('booking.phone_limit', 'Nomor ini sudah mencapai batas janji aktif. Hubungi klinik bila perlu mengubahnya.');
            }
            if ($code !== null && $this->bookings->codesSentSince($phoneHash, $now->sub(new DateInterval('P1D'))) >= self::MAX_CODES_PER_PHONE_DAY) {
                throw new InvalidBooking('booking.code_limit', 'Terlalu banyak permintaan kode untuk nomor ini hari ini. Coba lagi besok.');
            }

            $start = $this->time->parse($date, $time);
            $id = $this->ids->generate();
            $this->bookings->add(
                $id, hash('sha256', $token), $doctorId, $start, $start->add(new DateInterval('PT'.$settings->slotMinutes.'M')), $name, $phone, $phoneHash, $complaint,
                $code === null ? BookingStatus::Pending : BookingStatus::AwaitingCode, $now->add(new DateInterval('PT'.$settings->holdHours.'H')),
                $code === null ? null : $this->codeHash($id, $code), $code === null ? null : $now->add(new DateInterval('PT'.self::CODE_TTL_MINUTES.'M')),
                $ip === null ? null : $this->pii->blindIndex('ip', $ip),
            );
            $this->audit->record(new AuditEntry(null, 'booking.requested', 'booking_request', $id, null, ['doctor_id' => $doctorId, 'starts_at' => $start->format('Y-m-d\TH:i:s\Z'), 'verification' => $code !== null]));

            return $id;
        });

        if ($code !== null) {
            $this->deliver($id, $phone, $code, (string) $settings->otpTemplate);

            return ['token' => $token, 'next' => 'verify'];
        }
        $this->raiseStaffAlert($id);

        return ['token' => $token, 'next' => 'status'];
    }

    /**
     * @throws InvalidBooking
     */
    public function verify(string $token, string $code): void
    {
        $failure = $this->tx->run(function () use ($token, $code): ?InvalidBooking {
            $record = $this->byToken($token);
            $record = $this->bookings->lockForUpdate($record->id) ?? throw new InvalidBooking('booking.not_found', 'Permintaan booking tidak ditemukan.');
            if ($record->status === BookingStatus::Pending && $record->verified) {
                return null;
            }
            if ($record->status !== BookingStatus::AwaitingCode) {
                return new InvalidBooking('booking.not_verifiable', 'Permintaan ini tidak lagi menunggu kode verifikasi.');
            }
            $now = $this->clock->now();
            if ($record->expiresAt <= $now || $record->codeExpiresAt === null || $record->codeExpiresAt <= $now) {
                return new InvalidBooking('booking.code_expired', 'Kode sudah kedaluwarsa. Minta kode baru.');
            }
            if ($record->codeAttempts >= self::MAX_CODE_ATTEMPTS) {
                return new InvalidBooking('booking.code_attempts', 'Terlalu banyak percobaan. Minta kode baru.');
            }
            if ($record->codeHash === null || ! hash_equals($record->codeHash, $this->codeHash($record->id, trim($code)))) {
                $this->bookings->bumpAttempts($record->id);

                return new InvalidBooking('booking.code_wrong', 'Kode verifikasi salah.');
            }
            $this->bookings->markVerified($record->id, $now);
            $this->audit->record(new AuditEntry(null, 'booking.verified', 'booking_request', $record->id, null, null));

            return null;
        });

        if ($failure !== null) {
            throw $failure;
        }
        $record = $this->byToken($token);
        $this->raiseStaffAlert($record->id);
    }

    public function resendCode(string $token): void
    {
        $settings = $this->bookings->settings();
        $sent = $this->tx->run(function () use ($token, $settings): array {
            $record = $this->byToken($token);
            $record = $this->bookings->lockForUpdate($record->id) ?? throw new InvalidBooking('booking.not_found', 'Permintaan booking tidak ditemukan.');
            if ($record->status !== BookingStatus::AwaitingCode || ! $this->verificationAvailable($settings)) {
                throw new InvalidBooking('booking.not_verifiable', 'Permintaan ini tidak lagi menunggu kode verifikasi.');
            }
            $now = $this->clock->now();
            $phoneHash = $this->bookings->phoneHashOf($record->id) ?? '';
            if ($record->codeSentCount >= self::MAX_CODES_PER_REQUEST || $this->bookings->codesSentSince($phoneHash, $now->sub(new DateInterval('P1D'))) >= self::MAX_CODES_PER_PHONE_DAY) {
                throw new InvalidBooking('booking.code_limit', 'Batas pengiriman kode tercapai. Hubungi klinik bila perlu.');
            }
            $code = (string) random_int(100000, 999999);
            $this->bookings->setCode($record->id, $this->codeHash($record->id, $code), $now->add(new DateInterval('PT'.self::CODE_TTL_MINUTES.'M')));

            return [$record->id, $this->bookings->phoneOf($record->id) ?? '', $code];
        });

        $this->deliver($sent[0], $sent[1], $sent[2], (string) $settings->otpTemplate);
    }

    /**
     * @return array<string, mixed>
     */
    public function status(string $token): array
    {
        $record = $this->byToken($token);
        $doctor = $this->doctors->byIds([$record->doctorId])[$record->doctorId]->name ?? 'Dokter';
        $start = $this->time->toLocal($record->startsAt);
        $first = explode(' ', $record->patientName)[0];

        return [
            'status' => $record->status->value,
            'status_label' => $record->status->label(),
            'first_name' => $first,
            'doctor' => $doctor,
            'date' => $start->format('Y-m-d'),
            'time' => $start->format('H:i'),
            'minutes' => (int) (($record->endsAt->getTimestamp() - $record->startsAt->getTimestamp()) / 60),
            'verified' => $record->verified,
            'can_cancel' => $record->status->holdsSlot(),
            'can_verify' => $record->status === BookingStatus::AwaitingCode,
            'note' => $record->status === BookingStatus::Rejected ? $record->decisionNote : null,
        ];
    }

    public function cancel(string $token): void
    {
        $this->tx->run(function () use ($token): void {
            $record = $this->byToken($token);
            $record = $this->bookings->lockForUpdate($record->id) ?? throw new InvalidBooking('booking.not_found', 'Permintaan booking tidak ditemukan.');
            if (! $record->status->holdsSlot()) {
                throw new InvalidBooking('booking.cannot_cancel', 'Permintaan ini tidak dapat dibatalkan dari sini. Hubungi klinik.');
            }
            $this->bookings->transition($record->id, BookingStatus::Cancelled, null, 'Dibatalkan oleh pasien.', null, null, $this->clock->now());
            $this->audit->record(new AuditEntry(null, 'booking.cancelled_by_patient', 'booking_request', $record->id, null, null));
        });
    }

    private function verificationAvailable(BookingSettings $settings): bool
    {
        return $settings->hasOtp() && $this->sender->available();
    }

    private function assertDate(string $date, BookingSettings $settings): void
    {
        try {
            $this->time->parse($date, '00:00');
        } catch (InvalidAppointment) {
            throw new InvalidBooking('booking.date_invalid', 'Tanggal tidak valid.');
        }
        $today = $this->time->today();
        $last = (new DateTimeImmutable($today))->add(new DateInterval('P'.$settings->horizonDays.'D'))->format('Y-m-d');
        if ($date < $today || $date > $last) {
            throw new InvalidBooking('booking.date_out_of_range', 'Tanggal di luar rentang yang dapat dipesan.');
        }
    }

    private function byToken(string $token): BookingRecord
    {
        if (preg_match('/^[a-f0-9]{40}$/', $token) !== 1) {
            throw new InvalidBooking('booking.not_found', 'Permintaan booking tidak ditemukan.');
        }

        return $this->bookings->findByTokenHash(hash('sha256', $token)) ?? throw new InvalidBooking('booking.not_found', 'Permintaan booking tidak ditemukan.');
    }

    private function codeHash(string $id, string $code): string
    {
        return $this->pii->blindIndex('booking-code', $id.':'.$code);
    }

    /**
     * Kode dikirim setelah komit. Bila gagal, permintaan dibatalkan agar tidak menahan slot tanpa bisa diverifikasi.
     */
    private function deliver(string $id, string $phone, string $code, string $template): void
    {
        try {
            $this->sender->send($phone, $code, $template);
        } catch (BookingCodeNotSent $e) {
            $this->tx->run(fn () => $this->bookings->transition($id, BookingStatus::Cancelled, null, 'Kode verifikasi gagal dikirim.', null, null, $this->clock->now()));

            throw new InvalidBooking('booking.code_failed', 'Kode verifikasi gagal dikirim. Coba lagi nanti atau hubungi klinik.');
        }
    }

    private function raiseStaffAlert(string $id): void
    {
        $this->alerts->raise(new Notification(
            'booking.requested',
            'Permintaan booking online',
            'Ada permintaan booking online yang menunggu konfirmasi.',
            '/booking-requests',
            'appointment.manage',
            'booking.requested:'.$id,
        ));
    }
}

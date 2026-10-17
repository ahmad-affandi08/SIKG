<?php

declare(strict_types=1);

namespace Src\Scheduling\Application;

use Src\Clinic\Contracts\DoctorDirectory;
use Src\Clinic\Contracts\LetterheadProvider;
use Src\Clinic\Contracts\ReminderSettings;
use Src\Patient\Contracts\PatientContactLookup;
use Src\Scheduling\Domain\AppointmentRepository;
use Src\Scheduling\Domain\AppointmentStatus;
use Src\Scheduling\Domain\ReminderLog;
use Src\Scheduling\Domain\ReminderPolicy;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Notifications\EmailSender;
use Src\Shared\Application\Notifications\TelegramSender;

/**
 * JDW-07: mengirim pengingat janji ke pasien lewat email dan Telegram (bukan WhatsApp). Hanya untuk pasien yang menyetujui
 * dan hanya bila klinik mengaktifkan pengingat. Idempoten: setiap (janji, jenis, saluran) dicatat dan tidak dikirim ulang;
 * kegagalan dicoba lagi pada putaran berikutnya hingga batas percobaan. Isi pesan hanya waktu dan dokter, tanpa diagnosis
 * atau tindakan (M11).
 */
final readonly class AppointmentReminders
{
    public function __construct(
        private AppointmentRepository $appointments,
        private ReminderLog $log,
        private ReminderSettings $settings,
        private PatientContactLookup $contacts,
        private DoctorDirectory $doctors,
        private LetterheadProvider $letterhead,
        private ClinicTime $time,
        private EmailSender $email,
        private TelegramSender $telegram,
        private Clock $clock,
    ) {}

    /**
     * @return array{sent: int, failed: int}
     */
    public function run(): array
    {
        $result = ['sent' => 0, 'failed' => 0];
        $emailReady = $this->email->configured();
        $telegramReady = $this->telegram->configured();
        if (! $this->settings->enabled() || (! $emailReady && ! $telegramReady)) {
            return $result;
        }

        $now = $this->clock->now();
        $nowLocal = $this->time->toLocal($now);
        [, $until] = $this->time->range($nowLocal->format('Y-m-d'), $nowLocal->modify('+1 day')->format('Y-m-d'));

        $due = [];
        foreach ($this->appointments->between($now, $until) as $appointment) {
            if ($appointment->status !== AppointmentStatus::Scheduled && $appointment->status !== AppointmentStatus::Confirmed) {
                continue;
            }
            $kind = ReminderPolicy::due($this->time->toLocal($appointment->startsAt), $nowLocal);
            if ($kind !== null) {
                $due[] = [$appointment, $kind];
            }
        }
        if ($due === []) {
            return $result;
        }

        $contacts = $this->contacts->reminderContacts(array_values(array_unique(array_map(fn (array $d) => $d[0]->patientId, $due))));
        $doctors = $this->doctors->byIds(array_values(array_unique(array_map(fn (array $d) => $d[0]->doctorId, $due))));
        $clinic = $this->letterhead->forDocument()->clinicName;

        foreach ($due as [$appointment, $kind]) {
            $contact = $contacts[$appointment->patientId] ?? null;
            if ($contact === null) {
                continue;
            }
            $startLocal = $this->time->toLocal($appointment->startsAt);
            $text = ReminderPolicy::message($kind, $clinic, $startLocal, $doctors[$appointment->doctorId]->name ?? 'dokter kami');

            $channels = [];
            if ($emailReady && $contact->email !== null) {
                $channels['email'] = fn (): bool => $this->email->send($contact->email, 'Pengingat janji kunjungan - '.$clinic, $text);
            }
            if ($telegramReady && $contact->telegramChatId !== null) {
                $channels['telegram'] = fn (): bool => $this->telegram->send($contact->telegramChatId, $text);
            }

            foreach ($channels as $channel => $send) {
                $state = $this->log->state($appointment->id, $kind, $channel);
                if ($state !== null && ($state['status'] === 'sent' || $state['attempts'] >= ReminderPolicy::MAX_ATTEMPTS)) {
                    continue;
                }
                $ok = $send();
                $this->log->record($appointment->id, $kind, $channel, $ok, $now);
                $result[$ok ? 'sent' : 'failed']++;
            }
        }

        return $result;
    }
}

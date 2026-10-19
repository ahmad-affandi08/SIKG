<?php

declare(strict_types=1);

namespace Src\Integration\Application\Reminder;

use Src\Integration\Domain\Reminder\InvalidReminder;
use Src\Integration\Domain\Reminder\ReminderMessage;
use Src\Integration\Domain\Reminder\ReminderRecord;
use Src\Integration\Domain\Reminder\ReminderRepository;
use Src\Integration\Domain\Reminder\ReminderSettingsRepository;
use Src\Integration\Domain\Reminder\ReminderStatus;
use Src\Patient\Contracts\PatientLookup;
use Src\Patient\Contracts\PatientPhoneLookup;
use Src\Shared\Application\AccessLogger;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Transaction;

/**
 * P2-03: meja pengingat bagi petugas. Menampilkan pengingat yang menunggu beserta pratinjau pesan, membuat tautan WhatsApp (nomor HP
 * hanya keluar saat petugas menekan tombol, dan pembukaannya tercatat di jejak akses), menandai terkirim, melewati, dan mencatat
 * pasien yang tidak mau diingatkan.
 */
final readonly class ReminderDesk
{
    public function __construct(
        private ReminderSettingsRepository $settings,
        private ReminderRepository $reminders,
        private PatientPhoneLookup $phones,
        private PatientLookup $patients,
        private ReminderContent $content,
        private AccessLogger $access,
        private AuditLogger $audit,
        private Transaction $tx,
        private Clock $clock,
    ) {}

    /**
     * @return array{enabled: bool, mode: string, pending: list<array<string, mixed>>, history: list<array<string, mixed>>, optouts: list<array<string, mixed>>}
     */
    public function overview(): array
    {
        $settings = $this->settings->get();
        $pending = $this->reminders->pendingDue($this->clock->now(), 100);
        $history = $this->reminders->history(50);
        $optouts = $this->reminders->optOutList();
        $ids = array_values(array_unique([...array_map(fn ($r) => $r->patientId, $pending), ...array_map(fn ($r) => $r->patientId, $history), ...$optouts]));
        $phones = $this->phones->phones($ids);
        $summaries = $this->patients->summaries($ids);

        $row = fn (ReminderRecord $r): array => [
            'id' => $r->id,
            'kind' => $r->kind->value,
            'kind_label' => $r->kind->label(),
            'patient_name' => $summaries[$r->patientId]->name ?? '-',
            'medical_record_no' => $summaries[$r->patientId]->medicalRecordNo ?? '-',
            'has_phone' => ($phones[$r->patientId]->phone ?? null) !== null,
            'status' => $r->status->value,
            'status_label' => $r->status->label(),
            'channel' => $r->channel,
            'sent_at' => $r->sentAt?->format('c'),
            'last_error' => $r->lastError,
            'due_at' => $r->dueAt->format('c'),
        ];

        $pendingRows = [];
        foreach ($pending as $reminder) {
            $patient = $phones[$reminder->patientId] ?? null;
            $content = $patient === null ? null : $this->content->build($reminder, $patient, $settings);
            if ($content === null) {
                $this->reminders->markSkipped($reminder->id, 'Janji sudah tidak aktif.');

                continue;
            }
            $pendingRows[] = $row($reminder) + ['message' => $content['message']];
        }

        return [
            'enabled' => $settings->enabled,
            'mode' => $settings->mode(),
            'pending' => $pendingRows,
            'history' => array_map($row, $history),
            'optouts' => array_map(fn (string $id) => ['patient_id' => $id, 'patient_name' => $summaries[$id]->name ?? '-', 'medical_record_no' => $summaries[$id]->medicalRecordNo ?? '-'], $optouts),
        ];
    }

    /**
     * Tautan WhatsApp untuk satu pengingat. Mencatat akses ke data pasien.
     *
     * @return array{url: string}
     *
     * @throws InvalidReminder
     */
    public function link(string $actorId, string $reminderId, string $route): array
    {
        $reminder = $this->pending($reminderId);
        $patient = $this->phones->phones([$reminder->patientId])[$reminder->patientId] ?? null;
        if ($patient === null || $patient->phone === null) {
            throw new InvalidReminder('Pasien belum memiliki nomor HP yang sah. Lengkapi di data pasien.');
        }
        $content = $this->content->build($reminder, $patient, $this->settings->get());
        if ($content === null) {
            $this->reminders->markSkipped($reminder->id, 'Janji sudah tidak aktif.');

            throw new InvalidReminder('Janji sudah tidak aktif, pengingat dilewati.');
        }
        $this->access->patientRecordOpened($reminder->patientId, $route);

        return ['url' => ReminderMessage::whatsappLink($patient->phone, $content['message'])];
    }

    public function markSent(string $actorId, string $reminderId): void
    {
        $reminder = $this->pending($reminderId);
        $this->tx->run(function () use ($actorId, $reminder): void {
            $this->reminders->markSent($reminder->id, 'link', $actorId, $this->clock->now());
            $this->audit->record(new AuditEntry($actorId, 'reminder.marked_sent', 'patient', $reminder->patientId, null, ['reminder_id' => $reminder->id, 'kind' => $reminder->kind->value]));
        });
    }

    public function skip(string $actorId, string $reminderId): void
    {
        $reminder = $this->pending($reminderId);
        $this->tx->run(function () use ($actorId, $reminder): void {
            $this->reminders->markSkipped($reminder->id, 'Dilewati petugas.');
            $this->audit->record(new AuditEntry($actorId, 'reminder.skipped', 'patient', $reminder->patientId, null, ['reminder_id' => $reminder->id]));
        });
    }

    public function optOut(string $actorId, string $reminderId): void
    {
        $reminder = $this->reminders->find($reminderId) ?? throw new InvalidReminder('Pengingat tidak ditemukan.');
        $this->tx->run(function () use ($actorId, $reminder): void {
            $this->reminders->optOut($actorId, $reminder->patientId);
            if ($reminder->status === ReminderStatus::Pending) {
                $this->reminders->markSkipped($reminder->id, 'Pasien tidak mau diingatkan.');
            }
            $this->audit->record(new AuditEntry($actorId, 'reminder.opted_out', 'patient', $reminder->patientId));
        });
    }

    public function optIn(string $actorId, string $patientId): void
    {
        $this->tx->run(function () use ($actorId, $patientId): void {
            $this->reminders->optIn($actorId, $patientId);
            $this->audit->record(new AuditEntry($actorId, 'reminder.opted_in', 'patient', $patientId));
        });
    }

    private function pending(string $reminderId): ReminderRecord
    {
        $reminder = $this->reminders->find($reminderId) ?? throw new InvalidReminder('Pengingat tidak ditemukan.');
        if ($reminder->status !== ReminderStatus::Pending) {
            throw new InvalidReminder('Pengingat ini sudah diproses.');
        }

        return $reminder;
    }
}

<?php

declare(strict_types=1);

namespace Src\Clinical\Application;

use DateTimeImmutable;
use Src\Clinical\Domain\EncounterLocked;
use Src\Clinical\Domain\EncounterRepository;
use Src\Clinical\Domain\InvalidClinicalData;
use Src\Clinical\Domain\NoteRecord;
use Src\Clinical\Domain\NoteSection;
use Src\Clinical\Domain\NoteType;
use Src\Clinical\Domain\Vitals;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Transaction;

/**
 * RM-02, RM-03, RM-07: anamnesis, catatan SOAP, dan addendum. Catatan tidak ditimpa: tiap perubahan membuat versi baru
 * yang menunjuk versi lama, sehingga riwayat versi selalu dapat ditelusuri.
 */
final readonly class EncounterNotes
{
    /** Bagian SOAP yang dapat ditulis: kunci masukan => [jenis, bagian]. */
    public const FIELDS = [
        'subjective' => [NoteType::Subjective, null],
        'extra_oral' => [NoteType::Objective, NoteSection::ExtraOral],
        'intra_oral' => [NoteType::Objective, NoteSection::IntraOral],
        'assessment' => [NoteType::Assessment, null],
        'plan' => [NoteType::Plan, null],
    ];

    public function __construct(
        private EncounterRepository $encounters,
        private EncounterGuard $guard,
        private Transaction $tx,
        private AuditLogger $audit,
        private Clock $clock,
    ) {}

    /**
     * RM-02: keluhan utama, riwayat keluhan, dan tanda vital oleh perawat atau dokter.
     */
    public function recordAnamnesis(string $actorId, string $encounterId, ?string $chiefComplaint, ?string $complaintHistory, Vitals $vitals, bool $mayCorrectClosed): void
    {
        $this->tx->run(function () use ($actorId, $encounterId, $chiefComplaint, $complaintHistory, $vitals, $mayCorrectClosed): void {
            $encounter = $this->guard->load($encounterId);
            $now = $this->clock->now();
            $chief = $this->clean($chiefComplaint, 2000);
            $history = $this->clean($complaintHistory, 4000);

            $this->guard->assertCorrectable($encounter, $encounter->anamnesisAt ?? $now, $mayCorrectClosed);

            $before = ['chief_complaint' => $encounter->chiefComplaint, 'complaint_history' => $encounter->complaintHistory, 'vitals' => $encounter->vitals->toArray()];
            $after = ['chief_complaint' => $chief, 'complaint_history' => $history, 'vitals' => $vitals->toArray()];
            if ($before === $after) {
                return;
            }

            $this->encounters->saveAnamnesis($actorId, $encounterId, $chief, $history, $vitals, $encounter->anamnesisAt ?? $now);
            $this->audit->record(new AuditEntry($actorId, 'encounter.anamnesis_saved', 'encounter', $encounterId, $before, $after));
        });
    }

    /**
     * RM-03: menyimpan bagian SOAP yang berubah. Bagian yang tidak berubah tidak membuat versi baru.
     *
     * @param  array<string, string|null>  $fields  kunci dari self::FIELDS
     */
    public function writeNotes(string $actorId, string $encounterId, array $fields, bool $mayCorrectClosed): void
    {
        $this->tx->run(function () use ($actorId, $encounterId, $fields, $mayCorrectClosed): void {
            $encounter = $this->guard->load($encounterId);
            $now = $this->clock->now();
            $current = $this->currentByKey($encounterId);

            foreach (self::FIELDS as $key => [$type, $section]) {
                if (! array_key_exists($key, $fields)) {
                    continue;
                }
                $body = $this->clean($fields[$key], 8000) ?? '';
                $existing = isset($current[$key]) ? $current[$key] : null;
                if ($existing !== null && $existing->body === $body || $existing === null && $body === '') {
                    continue;
                }

                if ($existing === null) {
                    $this->guard->assertOpen($encounter);
                    $this->encounters->addNote($actorId, $encounterId, $type, $section, $body, $now);
                    $this->audit->record(new AuditEntry($actorId, 'encounter.note_written', 'encounter', $encounterId, null, ['field' => $key]));

                    continue;
                }

                $this->guard->assertCorrectable($encounter, $this->rootAuthoredAt($encounterId, $existing), $mayCorrectClosed);
                $this->encounters->addNote($actorId, $encounterId, $type, $section, $body, $now, $existing->id);
                $this->audit->record(new AuditEntry($actorId, 'encounter.note_revised', 'encounter', $encounterId, ['field' => $key, 'version_id' => $existing->id], ['field' => $key]));
            }
        });
    }

    /**
     * RM-07: addendum pada kunjungan yang sudah ditutup, bertanda waktu dan nama penulis. Alasan wajib.
     */
    public function addAddendum(string $actorId, string $encounterId, string $body, string $reason): string
    {
        $body = $this->clean($body, 8000);
        $reason = $this->clean($reason, 255);
        if ($body === null || $reason === null) {
            throw new InvalidClinicalData('encounter.addendum_incomplete', 'Isi addendum dan alasannya wajib diisi.');
        }

        return $this->tx->run(function () use ($actorId, $encounterId, $body, $reason): string {
            $encounter = $this->guard->load($encounterId);
            if (! $encounter->isClosed()) {
                throw new EncounterLocked('Addendum hanya untuk kunjungan yang sudah ditutup. Ubah catatan langsung selama kunjungan berjalan.');
            }

            $id = $this->encounters->addNote($actorId, $encounterId, NoteType::Addendum, null, $body, $this->clock->now(), null, $reason);
            $this->audit->record(new AuditEntry($actorId, 'encounter.addendum_added', 'encounter', $encounterId, null, ['note_id' => $id, 'reason' => $reason]));

            return $id;
        });
    }

    /**
     * @return array<string, NoteRecord> kunci SOAP => versi terkini
     */
    private function currentByKey(string $encounterId): array
    {
        $out = [];
        foreach ($this->encounters->currentNotes($encounterId) as $note) {
            foreach (self::FIELDS as $key => [$type, $section]) {
                if ($note->type === $type && $note->section === $section) {
                    $out[$key] = $note;
                }
            }
        }

        return $out;
    }

    /** Waktu input pertama dari rantai versi (batas 2x24 jam dihitung sejak input pertama). */
    private function rootAuthoredAt(string $encounterId, NoteRecord $note): DateTimeImmutable
    {
        $cursor = $note;
        while ($cursor->supersedesId !== null) {
            $previous = $this->encounters->findNote($encounterId, $cursor->supersedesId);
            if ($previous === null) {
                break;
            }
            $cursor = $previous;
        }

        return $cursor->authoredAt;
    }

    private function clean(?string $text, int $max): ?string
    {
        $text = $text === null ? null : trim($text);
        if ($text === null || $text === '') {
            return null;
        }
        if (mb_strlen($text) > $max) {
            throw new InvalidClinicalData('clinical.text_too_long', "Teks terlalu panjang (maksimal {$max} karakter).");
        }

        return $text;
    }
}

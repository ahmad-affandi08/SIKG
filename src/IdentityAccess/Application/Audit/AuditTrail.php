<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Application\Audit;

use DateTimeImmutable;
use DateTimeZone;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\IdentityAccess\Contracts\UserLookup;
use Src\Patient\Contracts\PatientLookup;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\AuditTrailReader;

/**
 * SYS-01: halaman audit untuk Pemilik dan Admin (izin audit.view diperiksa di route). Menampilkan siapa, kapan, aksi apa, serta
 * nilai sebelum dan sesudah, ditambah jejak pembukaan rekam medis pasien. Hanya membaca; membuka detail yang memuat nilai
 * sebelum dan sesudah ikut dicatat sebagai audit.
 */
final readonly class AuditTrail
{
    public const PER_PAGE = 50;

    public function __construct(
        private AuditTrailReader $reader,
        private UserLookup $users,
        private PatientLookup $patients,
        private ClinicCalendar $calendar,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array{action?: string|null, subject_type?: string|null, subject_id?: string|null, actor_id?: string|null, from?: string|null, to?: string|null}  $filters  tanggal lokal klinik YYYY-MM-DD
     * @return array<string, mixed>
     */
    public function list(array $filters, int $page): array
    {
        $result = $this->reader->entries($this->withRange($filters), $page, self::PER_PAGE);
        $names = $this->users->names($this->ids(array_column($result['rows'], 'actor_id')));

        $actorIds = $this->reader->actorIds();
        $actorNames = $this->users->names($actorIds);
        $actors = [];
        foreach ($actorIds as $id) {
            $actors[] = ['value' => $id, 'label' => $actorNames[$id] ?? 'Pengguna dihapus'];
        }
        usort($actors, fn (array $a, array $b) => strcmp($a['label'], $b['label']));

        return [
            'rows' => array_map(fn (array $r) => [...$r, 'actor_name' => $r['actor_id'] === null ? null : ($names[$r['actor_id']] ?? null)], $result['rows']),
            'total' => $result['total'],
            'page' => $result['page'],
            'per_page' => $result['per_page'],
            'subject_types' => $this->reader->subjectTypes(),
            'actors' => $actors,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detail(string $actorId, string $id): ?array
    {
        $entry = $this->reader->entry($id);
        if ($entry === null) {
            return null;
        }
        $this->audit->record(new AuditEntry($actorId, 'audit.viewed', 'audit_log', $id));
        $names = $this->users->names($this->ids([$entry['actor_id']]));

        $before = is_array($entry['before']) ? $entry['before'] : [];
        $after = is_array($entry['after']) ? $entry['after'] : [];
        $fields = [];
        foreach (array_values(array_unique([...array_keys($before), ...array_keys($after)])) as $key) {
            $old = array_key_exists($key, $before) ? self::text($before[$key]) : null;
            $new = array_key_exists($key, $after) ? self::text($after[$key]) : null;
            $fields[] = ['field' => (string) $key, 'before' => $old, 'after' => $new, 'changed' => $old !== $new];
        }

        return [
            'id' => $entry['id'],
            'actor_name' => $entry['actor_id'] === null ? null : ($names[$entry['actor_id']] ?? null),
            'action' => $entry['action'],
            'subject_type' => $entry['subject_type'],
            'subject_id' => $entry['subject_id'],
            'ip' => $entry['ip'],
            'user_agent' => $entry['user_agent'],
            'created_at' => $entry['created_at'],
            'fields' => $fields,
        ];
    }

    /**
     * @param  array{actor_id?: string|null, from?: string|null, to?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function accesses(array $filters, int $page): array
    {
        $result = $this->reader->accesses($this->withRange($filters), $page, self::PER_PAGE);
        $names = $this->users->names($this->ids(array_column($result['rows'], 'actor_id')));
        $patients = $this->patients->summaries($this->ids(array_column($result['rows'], 'patient_id')));

        $actorIds = $this->reader->actorIds();
        $actorNames = $this->users->names($actorIds);
        $actors = [];
        foreach ($actorIds as $id) {
            $actors[] = ['value' => $id, 'label' => $actorNames[$id] ?? 'Pengguna dihapus'];
        }

        return [
            'rows' => array_map(fn (array $r) => [
                ...$r,
                'actor_name' => $r['actor_id'] === null ? null : ($names[$r['actor_id']] ?? null),
                'patient_name' => $r['patient_id'] === null ? null : (($patients[$r['patient_id']] ?? null)?->name),
                'medical_record_no' => $r['patient_id'] === null ? null : (($patients[$r['patient_id']] ?? null)?->medicalRecordNo),
            ], $result['rows']),
            'total' => $result['total'],
            'page' => $result['page'],
            'per_page' => $result['per_page'],
            'actors' => $actors,
        ];
    }

    /**
     * Tanggal lokal klinik menjadi rentang UTC: awal hari `from` sampai awal hari setelah `to`.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function withRange(array $filters): array
    {
        $zone = new DateTimeZone($this->calendar->timezone());
        $utc = new DateTimeZone('UTC');
        $filters['from'] = ($filters['from'] ?? null) === null || $filters['from'] === '' ? null : (new DateTimeImmutable($filters['from'].' 00:00:00', $zone))->setTimezone($utc);
        $filters['to'] = ($filters['to'] ?? null) === null || $filters['to'] === '' ? null : (new DateTimeImmutable($filters['to'].' 00:00:00', $zone))->modify('+1 day')->setTimezone($utc);

        return $filters;
    }

    /**
     * @param  array<int, mixed>  $values
     * @return list<string>
     */
    private function ids(array $values): array
    {
        $ids = [];
        foreach ($values as $value) {
            if (is_string($value) && $value !== '') {
                $ids[$value] = $value;
            }
        }

        return array_values($ids);
    }

    private static function text(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'ya' : 'tidak';
        }
        if ($value === null) {
            return '-';
        }

        return is_scalar($value) ? (string) $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

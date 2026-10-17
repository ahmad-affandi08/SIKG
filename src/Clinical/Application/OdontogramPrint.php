<?php

declare(strict_types=1);

namespace Src\Clinical\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\Clinic\Contracts\LetterheadProvider;
use Src\Clinical\Domain\EncounterNotFound;
use Src\Patient\Contracts\PatientLookup;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;

/**
 * ODO-06: data cetak odontogram terkini pasien ke PDF. Setiap gigi memuat warna lima permukaan dan kondisi seluruh gigi dari
 * daftar kondisi klinik; gigi sulung dicetak hanya bila ada yang tercatat. Pencetakan tercatat di access log dan audit (M10).
 */
final readonly class OdontogramPrint
{
    private const PERMANENT_ROWS = [[18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28], [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38]];

    private const PRIMARY_ROWS = [[55, 54, 53, 52, 51, 61, 62, 63, 64, 65], [85, 84, 83, 82, 81, 71, 72, 73, 74, 75]];

    public function __construct(
        private OdontogramService $odontogram,
        private PatientLookup $patients,
        private LetterheadProvider $letterhead,
        private ClinicCalendar $calendar,
        private AuditLogger $audit,
        private Clock $clock,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function data(string $actorId, string $patientId): array
    {
        $patient = $this->patients->summaries([$patientId])[$patientId] ?? throw new EncounterNotFound;
        $current = $this->odontogram->current($patientId, 'odontogram.print');
        $colors = [];
        $names = [];
        foreach ($current['conditions'] as $condition) {
            $colors[(string) $condition['code']] = (string) $condition['color'];
            $names[(string) $condition['code']] = (string) $condition['name'];
        }

        $cell = function (int $fdi) use ($current, $colors): array {
            $state = $current['teeth'][$fdi] ?? null;
            $whole = $state['whole'] ?? null;
            $surfaces = [];
            foreach ((array) ($state['surfaces'] ?? []) as $code => $entry) {
                $surfaces[(string) $code] = (string) $entry['condition_code'];
            }
            $wholeCode = $whole === null ? null : (string) $whole['condition_code'];

            return [
                'fdi' => $fdi,
                'mesial_right' => in_array(intdiv($fdi, 10), [1, 4, 5, 8], true),
                'whole' => $wholeCode,
                'whole_color' => $wholeCode === null ? null : ($colors[$wholeCode] ?? '#9CA3AF'),
                'surfaces' => array_map(fn (string $code) => ['code' => $code, 'color' => $colors[$code] ?? '#9CA3AF'], $surfaces),
            ];
        };

        $hasPrimary = false;
        foreach (array_keys($current['teeth']) as $fdi) {
            $hasPrimary = $hasPrimary || intdiv((int) $fdi, 10) >= 5;
        }
        $rows = [['title' => 'Gigi dewasa', 'upper' => array_map($cell, self::PERMANENT_ROWS[0]), 'lower' => array_map($cell, self::PERMANENT_ROWS[1])]];
        if ($hasPrimary) {
            $rows[] = ['title' => 'Gigi sulung', 'upper' => array_map($cell, self::PRIMARY_ROWS[0]), 'lower' => array_map($cell, self::PRIMARY_ROWS[1])];
        }

        $details = [];
        foreach ($current['teeth'] as $fdi => $state) {
            $parts = [];
            if ($state['whole'] !== null) {
                $parts[] = 'seluruh gigi: '.($names[(string) $state['whole']['condition_code']] ?? (string) $state['whole']['condition_code']);
            }
            foreach ((array) $state['surfaces'] as $code => $entry) {
                $parts[] = $code.': '.($names[(string) $entry['condition_code']] ?? (string) $entry['condition_code']);
            }
            if ($parts !== []) {
                $details[] = ['fdi' => (int) $fdi, 'text' => implode(', ', $parts)];
            }
        }

        $legend = array_values(array_filter(array_map(
            fn (array $c) => ['code' => (string) $c['code'], 'name' => (string) $c['name'], 'color' => (string) $c['color']],
            array_filter($current['conditions'], fn (array $c) => (bool) $c['active'] && $c['code'] !== 'SEH'),
        )));

        $this->audit->record(new AuditEntry($actorId, 'odontogram.printed', 'patient', $patientId, null, ['teeth_recorded' => count($current['teeth'])]));
        $head = $this->letterhead->forDocument();

        return [
            'letterhead' => $head->toArray(),
            'patient' => ['name' => $patient->name, 'medical_record_no' => $patient->medicalRecordNo, 'birth_date' => $patient->birthDate === null ? null : (new DateTimeImmutable($patient->birthDate))->format('d/m/Y')],
            'rows' => $rows,
            'details' => $details,
            'legend' => $legend,
            'printed_at' => $this->clock->now()->setTimezone(new DateTimeZone($this->calendar->timezone()))->format('d/m/Y H:i'),
            'empty' => $current['teeth'] === [],
        ];
    }
}

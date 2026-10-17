<?php

declare(strict_types=1);

namespace Src\Integration\Domain\Fhir;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Menyusun Bundle transaksi FHIR R4 untuk satu kunjungan rawat jalan (P2-01): Encounter, satu Condition per diagnosa ICD-10,
 * dan satu Procedure per tindakan ber-ICD-9-CM. Pasien dan praktisi dirujuk lewat ID SATUSEHAT (IHS number) hasil pencarian NIK.
 * Murni: tanpa jaringan dan tanpa basis data. UUID entri diturunkan dari ID kunjungan agar bundel yang sama selalu identik.
 */
final class EncounterBundle
{
    /**
     * @param  list<array{code: string, name: string}>  $diagnoses  ICD-10
     * @param  list<array{code: string, name: string}>  $procedures  ICD-9-CM
     * @return array<string, mixed>
     */
    public static function build(
        string $organizationId,
        string $locationId,
        ?string $locationName,
        string $encounterId,
        string $patientIhs,
        string $patientName,
        string $practitionerIhs,
        string $practitionerName,
        DateTimeImmutable $startedAt,
        DateTimeImmutable $endedAt,
        array $diagnoses,
        array $procedures,
    ): array {
        $start = self::fhirTime($startedAt);
        $end = self::fhirTime($endedAt);
        $subject = ['reference' => 'Patient/'.$patientIhs, 'display' => $patientName];
        $encounterUrl = self::uuid($encounterId.'|encounter');

        $encounter = [
            'resourceType' => 'Encounter',
            'status' => 'finished',
            'class' => ['system' => 'http://terminology.hl7.org/CodeSystem/v3-ActCode', 'code' => 'AMB', 'display' => 'ambulatory'],
            'subject' => $subject,
            'participant' => [[
                'type' => [['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/v3-ParticipationType', 'code' => 'ATND', 'display' => 'attender']]]],
                'individual' => ['reference' => 'Practitioner/'.$practitionerIhs, 'display' => $practitionerName],
            ]],
            'period' => ['start' => $start, 'end' => $end],
            'location' => [['location' => array_filter(['reference' => 'Location/'.$locationId, 'display' => $locationName]), 'period' => ['start' => $start, 'end' => $end]]],
            'statusHistory' => [
                ['status' => 'in-progress', 'period' => ['start' => $start, 'end' => $end]],
                ['status' => 'finished', 'period' => ['start' => $end, 'end' => $end]],
            ],
            'serviceProvider' => ['reference' => 'Organization/'.$organizationId],
            'identifier' => [['system' => 'http://sys-ids.kemkes.go.id/encounter/'.$organizationId, 'value' => $encounterId]],
        ];

        $entries = [self::entry($encounterUrl, $encounter)];
        $encounterRef = ['reference' => $encounterUrl];

        foreach ($diagnoses as $index => $diagnosis) {
            $entries[] = self::entry(self::uuid($encounterId.'|condition|'.$index), [
                'resourceType' => 'Condition',
                'clinicalStatus' => ['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/condition-clinical', 'code' => 'active', 'display' => 'Active']]],
                'category' => [['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/condition-category', 'code' => 'encounter-diagnosis', 'display' => 'Encounter Diagnosis']]]],
                'code' => ['coding' => [['system' => 'http://hl7.org/fhir/sid/icd-10', 'code' => $diagnosis['code'], 'display' => $diagnosis['name']]]],
                'subject' => $subject,
                'encounter' => $encounterRef + ['display' => 'Kunjungan rawat jalan'],
                'onsetDateTime' => $start,
                'recordedDate' => $end,
            ]);
        }

        foreach ($procedures as $index => $procedure) {
            $entries[] = self::entry(self::uuid($encounterId.'|procedure|'.$index), [
                'resourceType' => 'Procedure',
                'status' => 'completed',
                'category' => ['coding' => [['system' => 'http://snomed.info/sct', 'code' => '277132007', 'display' => 'Therapeutic procedure']]],
                'code' => ['coding' => [['system' => 'http://hl7.org/fhir/sid/icd-9-cm', 'code' => $procedure['code'], 'display' => $procedure['name']]]],
                'subject' => $subject,
                'encounter' => $encounterRef,
                'performedPeriod' => ['start' => $start, 'end' => $end],
                'performer' => [['actor' => ['reference' => 'Practitioner/'.$practitionerIhs, 'display' => $practitionerName]]],
            ]);
        }

        return ['resourceType' => 'Bundle', 'type' => 'transaction', 'entry' => $entries];
    }

    /** Format waktu FHIR: UTC dengan offset eksplisit. */
    private static function fhirTime(DateTimeImmutable $at): string
    {
        return $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:sP');
    }

    /**
     * @param  array<string, mixed>  $resource
     * @return array{fullUrl: string, resource: array<string, mixed>, request: array{method: string, url: string}}
     */
    private static function entry(string $fullUrl, array $resource): array
    {
        return ['fullUrl' => $fullUrl, 'resource' => $resource, 'request' => ['method' => 'POST', 'url' => (string) $resource['resourceType']]];
    }

    /** UUID berversi 5 dari benih, deterministik. */
    private static function uuid(string $seed): string
    {
        $hex = substr(sha1('sikg-satusehat|'.$seed), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return 'urn:uuid:'.substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20, 12);
    }
}

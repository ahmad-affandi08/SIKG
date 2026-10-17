<?php

declare(strict_types=1);

use Src\Integration\Domain\Fhir\EncounterBundle;

function bundelUji(array $diagnosa = [['code' => 'K02.1', 'name' => 'Karies dentin']], array $prosedur = []): array
{
    return EncounterBundle::build(
        'ORG-1', 'LOC-1', 'Poli Gigi', '01HXENCOUNTER0000000000000', 'P0001', 'Budi Santoso', 'N0001', 'drg. Sari',
        new DateTimeImmutable('2025-06-16 09:00:00', new DateTimeZone('Asia/Jakarta')),
        new DateTimeImmutable('2025-06-16 09:40:00', new DateTimeZone('Asia/Jakarta')),
        $diagnosa, $prosedur,
    );
}

it('P2-01 bundel transaksi memuat Encounter lebih dulu lalu Condition dan Procedure', function () {
    $bundel = bundelUji([['code' => 'K02.1', 'name' => 'Karies dentin'], ['code' => 'K04.0', 'name' => 'Pulpitis']], [['code' => '23.2', 'name' => 'Tambalan']]);

    expect($bundel['resourceType'])->toBe('Bundle')->and($bundel['type'])->toBe('transaction')
        ->and(array_map(fn ($e) => $e['resource']['resourceType'], $bundel['entry']))->toBe(['Encounter', 'Condition', 'Condition', 'Procedure'])
        ->and(array_map(fn ($e) => $e['request'], $bundel['entry'])[1])->toBe(['method' => 'POST', 'url' => 'Condition']);
});

it('P2-01 Encounter rawat jalan selesai merujuk pasien, praktisi, lokasi, dan organisasi', function () {
    $encounter = bundelUji()['entry'][0]['resource'];

    expect($encounter['status'])->toBe('finished')->and($encounter['class']['code'])->toBe('AMB')
        ->and($encounter['subject']['reference'])->toBe('Patient/P0001')
        ->and($encounter['participant'][0]['individual']['reference'])->toBe('Practitioner/N0001')
        ->and($encounter['location'][0]['location']['reference'])->toBe('Location/LOC-1')
        ->and($encounter['serviceProvider']['reference'])->toBe('Organization/ORG-1')
        ->and($encounter['identifier'][0])->toBe(['system' => 'http://sys-ids.kemkes.go.id/encounter/ORG-1', 'value' => '01HXENCOUNTER0000000000000'])
        ->and($encounter['period'])->toBe(['start' => '2025-06-16T02:00:00+00:00', 'end' => '2025-06-16T02:40:00+00:00'])
        ->and(array_column($encounter['statusHistory'], 'status'))->toBe(['in-progress', 'finished']);
});

it('P2-01 Condition memakai ICD-10 dan merujuk Encounter lewat UUID entri', function () {
    $bundel = bundelUji();
    $condition = $bundel['entry'][1]['resource'];

    expect($condition['code']['coding'][0])->toBe(['system' => 'http://hl7.org/fhir/sid/icd-10', 'code' => 'K02.1', 'display' => 'Karies dentin'])
        ->and($condition['category'][0]['coding'][0]['code'])->toBe('encounter-diagnosis')
        ->and($condition['encounter']['reference'])->toBe($bundel['entry'][0]['fullUrl'])
        ->and($bundel['entry'][0]['fullUrl'])->toMatch('/^urn:uuid:[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
});

it('P2-01 Procedure memakai ICD-9-CM dan dikerjakan praktisi yang sama', function () {
    $prosedur = bundelUji([], [['code' => '23.2', 'name' => 'Tambalan']])['entry'][1]['resource'];

    expect($prosedur['resourceType'])->toBe('Procedure')->and($prosedur['status'])->toBe('completed')
        ->and($prosedur['code']['coding'][0]['system'])->toBe('http://hl7.org/fhir/sid/icd-9-cm')->and($prosedur['code']['coding'][0]['code'])->toBe('23.2')
        ->and($prosedur['performer'][0]['actor']['reference'])->toBe('Practitioner/N0001');
});

it('P2-01 bundel yang sama selalu identik karena UUID diturunkan dari kunjungan', function () {
    expect(bundelUji())->toBe(bundelUji());
});

<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Src\Clinic\Infrastructure\Eloquent\ProcedureModel;
use Src\Clinical\Domain\Events\EncounterClosed;
use Src\Clinical\Domain\Events\ProcedurePerformed;
use Src\Clinical\Domain\Events\ProcedureRetracted;
use Src\Clinical\Infrastructure\Eloquent\ClinicalNoteModel;
use Src\Clinical\Infrastructure\Eloquent\DiagnosisModel;
use Src\Clinical\Infrastructure\Eloquent\EncounterDiagnosisModel;
use Src\Clinical\Infrastructure\Eloquent\EncounterModel;
use Src\Clinical\Infrastructure\Eloquent\EncounterProcedureModel;
use Src\IdentityAccess\Domain\Role;
use Src\Patient\Infrastructure\Eloquent\PatientMedicalHistoryModel;
use Src\Scheduling\Infrastructure\Eloquent\AppointmentModel;
use Src\Shared\Infrastructure\Audit\AuditLogModel;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2025-06-16 07:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Janji yang sudah check-in sehingga kunjungan terbuat otomatis.
 *
 * @return array<string, mixed>
 */
function siapKunjungan(): array
{
    $s = siapJadwal();
    $janjiId = bukaJanji($s);
    test()->actingAs($s['user'])->post("/appointments/{$janjiId}/check-in")->assertSessionHasNoErrors();
    tenant()->set($s['klinik']->id);
    $kunjungan = EncounterModel::query()->where('appointment_id', $janjiId)->firstOrFail();

    return $s + [
        'janji' => $janjiId,
        'kunjungan' => $kunjungan->id,
        'dokter_user' => makeUser(Role::Dokter, $s['klinik']),
        'perawat' => makeUser(Role::Perawat, $s['klinik']),
    ];
}

function tindakan(array $s, array $o = []): ProcedureModel
{
    tenant()->set($s['klinik']->id);
    static $n = 0;
    $n++;
    $p = new ProcedureModel;
    $p->forceFill(['code' => "TDK-{$n}", 'name' => "Tindakan {$n}", 'scope' => 'tooth', 'default_price_amount' => 150000, 'is_active' => true, ...$o])->save();

    return $p;
}

function icd(string $kode = 'K02.1'): string
{
    return DiagnosisModel::query()->where('icd10_code', $kode)->firstOrFail()->id;
}

it('RM-01 kunjungan dibuat otomatis saat check-in dengan satu dokter utama', function () {
    $s = siapKunjungan();

    $k = EncounterModel::query()->findOrFail($s['kunjungan']);
    expect($k->status)->toBe('open')->and($k->doctor_id)->toBe($s['dokter']->id)->and($k->patient_id)->toBe($s['pasien'])
        ->and($k->appointment_id)->toBe($s['janji'])->and($k->chair_id)->toBe($s['kursi']->id);
    expect(AuditLogModel::query()->where('action', 'encounter.opened')->where('subject_id', $k->id)->exists())->toBeTrue();
});

it('RM-01 walk-in juga langsung memiliki kunjungan', function () {
    $s = siapJadwal();
    test()->actingAs($s['user'])->post('/walk-ins', ['patient_id' => $s['pasien'], 'doctor_id' => $s['dokter']->id])->assertSessionHasNoErrors();

    tenant()->set($s['klinik']->id);
    expect(EncounterModel::query()->count())->toBe(1);
});

it('RM-01 membuat kunjungan manual idempoten dan hanya untuk janji yang sudah check-in', function () {
    $s = siapKunjungan();
    $belum = bukaJanji($s, ['time' => '10:00', 'patient_id' => makePatient($s['klinik'], 'Citra Lestari'), 'chair_id' => null]);

    test()->actingAs($s['perawat'])->post('/encounters', ['appointment_id' => $s['janji']])->assertRedirect("/encounters/{$s['kunjungan']}");
    test()->actingAs($s['perawat'])->post('/encounters', ['appointment_id' => $belum])->assertSessionHasErrors('domain');

    tenant()->set($s['klinik']->id);
    expect(EncounterModel::query()->count())->toBe(1);
});

it('RM-01 layar pemeriksaan menampilkan banner alergi dan penyakit sistemik serta tercatat di access log', function () {
    $s = siapKunjungan();
    tenant()->set($s['klinik']->id);
    foreach ([['allergy', 'Penisilin', 'severe'], ['systemic_disease', 'Diabetes melitus', null], ['regular_medication', 'Metformin', null]] as [$tipe, $nama, $berat]) {
        (new PatientMedicalHistoryModel)->forceFill(['patient_id' => $s['pasien'], 'type' => $tipe, 'name' => $nama, 'severity' => $berat])->save();
    }

    test()->actingAs($s['perawat'])->get("/encounters/{$s['kunjungan']}")->assertOk()
        ->assertInertia(fn ($p) => $p->component('Encounters/Show')
            ->where('encounter.patient_name', 'Budi Santoso')
            ->has('warnings', 2)
            ->where('warnings.0.name', 'Penisilin')
            ->has('medical_history', 3)
            ->where('can.write', true)->where('can.close', false));

    expect(DB::table('access_logs')->where('patient_id', $s['pasien'])->where('actor_id', $s['perawat']->id)->exists())->toBeTrue();
});

it('RM-02 anamnesis menyimpan keluhan, riwayat keluhan, dan tanda vital dengan audit sebelum dan sesudah', function () {
    $s = siapKunjungan();

    test()->actingAs($s['perawat'])->put("/encounters/{$s['kunjungan']}/anamnesis", [
        'chief_complaint' => 'Gigi geraham kanan bawah sakit', 'complaint_history' => 'Sejak 3 hari, memberat malam hari',
        'systolic' => 120, 'diastolic' => 80, 'pulse' => 78, 'temperature_tenths' => 368,
    ])->assertSessionHasNoErrors();

    tenant()->set($s['klinik']->id);
    $k = EncounterModel::query()->findOrFail($s['kunjungan']);
    expect($k->chief_complaint)->toBe('Gigi geraham kanan bawah sakit')->and($k->vitals['temperature_tenths'])->toBe(368)->and($k->anamnesis_at)->not->toBeNull();
    $log = AuditLogModel::query()->where('action', 'encounter.anamnesis_saved')->firstOrFail();
    expect($log->before['chief_complaint'])->toBeNull()->and($log->after['vitals']['systolic'])->toBe(120);
});

it('RM-02 tanda vital di luar rentang atau tidak berpasangan ditolak', function () {
    $s = siapKunjungan();
    $kirim = fn (array $v) => test()->actingAs($s['perawat'])->put("/encounters/{$s['kunjungan']}/anamnesis", ['chief_complaint' => 'x'] + $v);

    $kirim(['systolic' => 400, 'diastolic' => 80])->assertSessionHasErrors('domain');
    $kirim(['systolic' => 120])->assertSessionHasErrors('domain');
    $kirim(['systolic' => 80, 'diastolic' => 120])->assertSessionHasErrors('domain');
    $kirim(['temperature_tenths' => 500])->assertSessionHasErrors('domain');
    $kirim(['systolic' => 120, 'diastolic' => 80, 'pulse' => 70, 'temperature_tenths' => 370])->assertSessionHasNoErrors();
});

it('RM-02 menyimpan ulang anamnesis yang sama tidak membuat audit baru', function () {
    $s = siapKunjungan();
    $data = ['chief_complaint' => 'Nyeri', 'pulse' => 70];

    test()->actingAs($s['perawat'])->put("/encounters/{$s['kunjungan']}/anamnesis", $data);
    test()->actingAs($s['perawat'])->put("/encounters/{$s['kunjungan']}/anamnesis", $data);

    tenant()->set($s['klinik']->id);
    expect(AuditLogModel::query()->where('action', 'encounter.anamnesis_saved')->count())->toBe(1);
});

it('RM-03 catatan SOAP disimpan per bagian dan perubahan menjadi versi baru tanpa menimpa', function () {
    $s = siapKunjungan();

    test()->actingAs($s['dokter_user'])->put("/encounters/{$s['kunjungan']}/notes", [
        'subjective' => 'Nyeri spontan', 'extra_oral' => 'Wajah simetris', 'intra_oral' => 'Karies dalam gigi 46', 'assessment' => 'Pulpitis', 'plan' => 'PSA',
    ])->assertSessionHasNoErrors();
    test()->actingAs($s['dokter_user'])->put("/encounters/{$s['kunjungan']}/notes", ['assessment' => 'Pulpitis ireversibel', 'plan' => 'PSA'])->assertSessionHasNoErrors();

    tenant()->set($s['klinik']->id);
    expect(ClinicalNoteModel::query()->where('encounter_id', $s['kunjungan'])->count())->toBe(6);
    test()->actingAs($s['dokter_user'])->get("/encounters/{$s['kunjungan']}")->assertInertia(fn ($p) => $p
        ->has('notes', 5)->has('note_history', 6)
        ->where('notes', fn ($notes) => collect($notes)->firstWhere('type', 'A')['body'] === 'Pulpitis ireversibel'));
});

it('RM-03 bagian objektif dibagi ekstra oral dan intra oral', function () {
    $s = siapKunjungan();
    test()->actingAs($s['dokter_user'])->put("/encounters/{$s['kunjungan']}/notes", ['extra_oral' => 'Normal', 'intra_oral' => 'Gingiva meradang']);

    tenant()->set($s['klinik']->id);
    expect(ClinicalNoteModel::query()->where('type', 'O')->pluck('section')->sort()->values()->all())->toBe(['extra_oral', 'intra_oral']);
});

it('RM-03 catatan kosong tidak membuat baris dan teks terlalu panjang ditolak', function () {
    $s = siapKunjungan();

    test()->actingAs($s['dokter_user'])->put("/encounters/{$s['kunjungan']}/notes", ['subjective' => '  ', 'plan' => null])->assertSessionHasNoErrors();
    test()->actingAs($s['dokter_user'])->put("/encounters/{$s['kunjungan']}/notes", ['plan' => str_repeat('a', 8001)])->assertSessionHasErrors('plan');

    tenant()->set($s['klinik']->id);
    expect(ClinicalNoteModel::query()->count())->toBe(0);
});

it('RM-03 baris catatan klinis tidak dapat diubah atau dihapus lewat aplikasi', function () {
    $s = siapKunjungan();
    test()->actingAs($s['dokter_user'])->put("/encounters/{$s['kunjungan']}/notes", ['plan' => 'Scaling']);
    tenant()->set($s['klinik']->id);
    $note = ClinicalNoteModel::query()->firstOrFail();

    expect(fn () => $note->update(['body' => 'diubah']))->toThrow(LogicException::class)
        ->and(fn () => $note->delete())->toThrow(LogicException::class);
});

it('RM-04 diagnosa ICD-10 boleh lebih dari satu dan dapat ditautkan ke gigi', function () {
    $s = siapKunjungan();

    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/diagnoses", ['diagnosis_id' => icd('K02.1'), 'tooth_fdi' => 46])->assertSessionHasNoErrors();
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/diagnoses", ['diagnosis_id' => icd('K04.0'), 'tooth_fdi' => 46, 'notes' => 'Ireversibel'])->assertSessionHasNoErrors();
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/diagnoses", ['diagnosis_id' => icd('K05.1')])->assertSessionHasNoErrors();

    tenant()->set($s['klinik']->id);
    $d = EncounterDiagnosisModel::query()->orderBy('icd10_code')->get();
    expect($d->pluck('icd10_code')->all())->toBe(['K02.1', 'K04.0', 'K05.1'])->and($d->pluck('tooth_fdi')->all())->toBe([46, 46, null])
        ->and($d[0]->diagnosis_name)->toBe('Karies dentin');
});

it('RM-04 diagnosa yang sama pada gigi yang sama, gigi tidak sah, dan kode nonaktif ditolak', function () {
    $s = siapKunjungan();
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/diagnoses", ['diagnosis_id' => icd(), 'tooth_fdi' => 16])->assertSessionHasNoErrors();

    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/diagnoses", ['diagnosis_id' => icd(), 'tooth_fdi' => 16])->assertSessionHasErrors('domain');
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/diagnoses", ['diagnosis_id' => icd(), 'tooth_fdi' => 19])->assertSessionHasErrors('domain');
    DiagnosisModel::query()->where('icd10_code', 'K04.0')->update(['is_active' => false]);
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/diagnoses", ['diagnosis_id' => icd('K04.0')])->assertSessionHasErrors('domain');
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/diagnoses", [])->assertSessionHasErrors('diagnosis_id');
});

it('RM-04 diagnosa dihapus dengan soft delete dan tercatat', function () {
    $s = siapKunjungan();
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/diagnoses", ['diagnosis_id' => icd()]);
    tenant()->set($s['klinik']->id);
    $entry = EncounterDiagnosisModel::query()->firstOrFail()->id;

    test()->actingAs($s['dokter_user'])->delete("/encounters/{$s['kunjungan']}/diagnoses/{$entry}")->assertRedirect();

    tenant()->set($s['klinik']->id);
    expect(EncounterDiagnosisModel::query()->count())->toBe(0)->and(EncounterDiagnosisModel::withTrashed()->count())->toBe(1)
        ->and(AuditLogModel::query()->where('action', 'encounter.diagnosis_removed')->exists())->toBeTrue();
});

it('RM-05 tindakan per gigi menyalin tarif katalog dan memicu event untuk tagihan', function () {
    Event::fake([ProcedurePerformed::class]);
    $s = siapKunjungan();
    $p = tindakan($s, ['scope' => 'tooth', 'default_price_amount' => 250000]);

    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/procedures", ['procedure_id' => $p->id, 'tooth_fdi' => 46, 'quantity' => 2, 'notes' => 'Anestesi infiltrasi'])->assertSessionHasNoErrors();

    tenant()->set($s['klinik']->id);
    $e = EncounterProcedureModel::query()->firstOrFail();
    expect($e->procedure_code)->toBe($p->code)->and($e->unit_price_amount)->toBe(250000)->and($e->quantity)->toBe(2)->and($e->tooth_fdi)->toBe(46)->and($e->performed_by)->toBe($s['dokter_user']->id);
    Event::assertDispatched(ProcedurePerformed::class, fn ($ev) => $ev->quantity === 2 && $ev->unitPriceAmount === 250000 && $ev->procedureCode === $p->code && $ev->patientId === $s['pasien']);
});

it('RM-05 mengubah tarif katalog sesudahnya tidak mengubah tindakan yang sudah dicatat', function () {
    $s = siapKunjungan();
    $p = tindakan($s, ['scope' => 'visit', 'default_price_amount' => 100000]);
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/procedures", ['procedure_id' => $p->id, 'quantity' => 1]);

    tenant()->set($s['klinik']->id);
    ProcedureModel::query()->whereKey($p->id)->update(['default_price_amount' => 900000, 'name' => 'Nama baru']);

    $e = EncounterProcedureModel::query()->firstOrFail();
    expect($e->unit_price_amount)->toBe(100000)->and($e->procedure_name)->not->toBe('Nama baru');
});

it('RM-05 sasaran tindakan mengikuti cakupan: gigi, permukaan, rahang, kunjungan', function () {
    $s = siapKunjungan();
    $gigi = tindakan($s, ['scope' => 'tooth']);
    $permukaan = tindakan($s, ['scope' => 'surface']);
    $rahang = tindakan($s, ['scope' => 'jaw']);
    $kunjungan = tindakan($s, ['scope' => 'visit']);
    $kirim = fn (array $d) => test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/procedures", $d + ['quantity' => 1]);

    $kirim(['procedure_id' => $gigi->id])->assertSessionHasErrors('domain');
    $kirim(['procedure_id' => $permukaan->id, 'tooth_fdi' => 16])->assertSessionHasErrors('domain');
    $kirim(['procedure_id' => $permukaan->id, 'tooth_fdi' => 16, 'surfaces' => ['X']])->assertSessionHasErrors('domain');
    $kirim(['procedure_id' => $rahang->id])->assertSessionHasErrors('domain');
    $kirim(['procedure_id' => $rahang->id, 'jaw' => 'atas'])->assertSessionHasErrors('domain');

    $kirim(['procedure_id' => $permukaan->id, 'tooth_fdi' => 16, 'surfaces' => ['O', 'M', 'O']])->assertSessionHasNoErrors();
    $kirim(['procedure_id' => $rahang->id, 'jaw' => 'upper'])->assertSessionHasNoErrors();
    $kirim(['procedure_id' => $kunjungan->id, 'tooth_fdi' => 16])->assertSessionHasNoErrors();

    tenant()->set($s['klinik']->id);
    $baris = EncounterProcedureModel::query()->orderBy('created_at')->orderBy('id')->get()->keyBy('scope');
    expect($baris['surface']->surfaces)->toBe('OM')->and($baris['jaw']->jaw)->toBe('upper')->and($baris['visit']->tooth_fdi)->toBeNull();
});

it('RM-05 jumlah dibatasi, tindakan nonaktif atau klinik lain ditolak', function () {
    $s = siapKunjungan();
    $p = tindakan($s);
    $nonaktif = tindakan($s, ['is_active' => false]);
    $lain = siapJadwal();
    $milikLain = tindakan($lain);
    $kirim = fn (array $d) => test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/procedures", $d);

    $kirim(['procedure_id' => $p->id, 'tooth_fdi' => 16, 'quantity' => 0])->assertSessionHasErrors('quantity');
    $kirim(['procedure_id' => $p->id, 'tooth_fdi' => 16, 'quantity' => 100])->assertSessionHasErrors('quantity');
    $kirim(['procedure_id' => $nonaktif->id, 'tooth_fdi' => 16, 'quantity' => 1])->assertSessionHasErrors('domain');
    $kirim(['procedure_id' => $milikLain->id, 'tooth_fdi' => 16, 'quantity' => 1])->assertSessionHasErrors('domain');
});

it('RM-05 tindakan dibatalkan dari kunjungan berjalan memicu event pelepasan item tagihan', function () {
    Event::fake([ProcedureRetracted::class]);
    $s = siapKunjungan();
    $p = tindakan($s, ['scope' => 'visit']);
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/procedures", ['procedure_id' => $p->id, 'quantity' => 1]);
    tenant()->set($s['klinik']->id);
    $entry = EncounterProcedureModel::query()->firstOrFail()->id;

    test()->actingAs($s['dokter_user'])->delete("/encounters/{$s['kunjungan']}/procedures/{$entry}")->assertRedirect();

    tenant()->set($s['klinik']->id);
    expect(EncounterProcedureModel::query()->count())->toBe(0)->and(EncounterProcedureModel::withTrashed()->count())->toBe(1);
    Event::assertDispatched(ProcedureRetracted::class, fn ($ev) => $ev->encounterProcedureId === $entry);
});

it('RM-07 menutup kunjungan menyelesaikan janji dan mengunci catatan', function () {
    Event::fake([EncounterClosed::class]);
    $s = siapKunjungan();
    test()->actingAs($s['dokter_user'])->put("/encounters/{$s['kunjungan']}/notes", ['plan' => 'Scaling']);

    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/close")->assertSessionHasNoErrors();

    tenant()->set($s['klinik']->id);
    $k = EncounterModel::query()->findOrFail($s['kunjungan']);
    expect($k->status)->toBe('closed')->and($k->closed_at)->not->toBeNull()->and($k->closed_by)->toBe($s['dokter_user']->id)
        ->and(AppointmentModel::query()->findOrFail($s['janji'])->status)->toBe('completed');
    Event::assertDispatched(EncounterClosed::class);
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/diagnoses", ['diagnosis_id' => icd()])->assertSessionHasErrors('domain');
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/close")->assertSessionHasErrors('domain');
});

it('RM-07 kunjungan kosong tidak dapat ditutup dan hanya dokter yang menutup', function () {
    $s = siapKunjungan();

    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/close")->assertSessionHasErrors('domain');
    test()->actingAs($s['perawat'])->post("/encounters/{$s['kunjungan']}/close")->assertForbidden();
});

it('RM-07 setelah ditutup, dokter memperbaiki catatan dalam 2x24 jam; perawat tidak', function () {
    $s = siapKunjungan();
    test()->actingAs($s['dokter_user'])->put("/encounters/{$s['kunjungan']}/notes", ['plan' => 'Scaling', 'assessment' => 'Gingivitis']);
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/close");

    test()->actingAs($s['perawat'])->put("/encounters/{$s['kunjungan']}/notes", ['plan' => 'Diubah perawat'])->assertSessionHasErrors('domain');
    test()->actingAs($s['dokter_user'])->put("/encounters/{$s['kunjungan']}/notes", ['plan' => 'Scaling dan edukasi'])->assertSessionHasNoErrors();
    // bagian baru yang belum pernah ditulis tidak bisa ditambahkan langsung setelah penutupan
    test()->actingAs($s['dokter_user'])->put("/encounters/{$s['kunjungan']}/notes", ['subjective' => 'Baru'])->assertSessionHasErrors('domain');

    tenant()->set($s['klinik']->id);
    expect(ClinicalNoteModel::query()->where('type', 'P')->count())->toBe(2);
});

it('RM-07 lewat 2x24 jam sejak input, perbaikan langsung ditolak dan hanya addendum yang boleh', function () {
    $s = siapKunjungan();
    test()->actingAs($s['dokter_user'])->put("/encounters/{$s['kunjungan']}/notes", ['plan' => 'Scaling']);
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/close");

    majuKe('2025-06-18 06:59:00');
    test()->actingAs($s['dokter_user'])->put("/encounters/{$s['kunjungan']}/notes", ['plan' => 'Scaling A'])->assertSessionHasNoErrors();
    majuKe('2025-06-18 07:01:00');
    test()->actingAs($s['dokter_user'])->put("/encounters/{$s['kunjungan']}/notes", ['plan' => 'Scaling B'])->assertSessionHasErrors('domain');
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/addenda", ['body' => 'Pasien kembali kontrol, keluhan hilang', 'reason' => 'Tindak lanjut'])->assertSessionHasNoErrors();

    tenant()->set($s['klinik']->id);
    expect(ClinicalNoteModel::query()->where('type', 'addendum')->count())->toBe(1);
});

it('RM-07 addendum wajib beralasan, hanya setelah ditutup, dan hanya oleh dokter', function () {
    $s = siapKunjungan();
    $kirim = fn (array $d) => test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/addenda", $d);

    $kirim(['body' => 'x', 'reason' => 'y'])->assertSessionHasErrors('domain');
    test()->actingAs($s['dokter_user'])->put("/encounters/{$s['kunjungan']}/notes", ['plan' => 'Scaling']);
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/close");

    $kirim(['body' => 'x'])->assertSessionHasErrors('reason');
    $kirim(['reason' => 'y'])->assertSessionHasErrors('body');
    test()->actingAs($s['perawat'])->post("/encounters/{$s['kunjungan']}/addenda", ['body' => 'x', 'reason' => 'y'])->assertForbidden();
    $kirim(['body' => 'Koreksi dosis anestesi', 'reason' => 'Salah ketik'])->assertSessionHasNoErrors();

    test()->actingAs($s['dokter_user'])->get("/encounters/{$s['kunjungan']}")->assertInertia(fn ($p) => $p->has('addenda', 1)->where('addenda.0.author', $s['dokter_user']->name)->where('addenda.0.reason', 'Salah ketik'));
});

it('RM-02 anamnesis setelah ditutup mengikuti aturan koreksi 2x24 jam', function () {
    $s = siapKunjungan();
    test()->actingAs($s['perawat'])->put("/encounters/{$s['kunjungan']}/anamnesis", ['chief_complaint' => 'Nyeri']);
    test()->actingAs($s['dokter_user'])->put("/encounters/{$s['kunjungan']}/notes", ['plan' => 'Scaling']);
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/close");

    test()->actingAs($s['perawat'])->put("/encounters/{$s['kunjungan']}/anamnesis", ['chief_complaint' => 'Nyeri hebat'])->assertSessionHasErrors('domain');
    test()->actingAs($s['dokter_user'])->put("/encounters/{$s['kunjungan']}/anamnesis", ['chief_complaint' => 'Nyeri hebat'])->assertSessionHasNoErrors();
    majuKe('2025-06-19 08:00:00');
    test()->actingAs($s['dokter_user'])->put("/encounters/{$s['kunjungan']}/anamnesis", ['chief_complaint' => 'Terlambat'])->assertSessionHasErrors('domain');
});

it('RM-01 memulai pemeriksaan menandai janji sedang diperiksa dan menetapkan kursi', function () {
    $s = siapKunjungan();
    $kursi2 = makeChair($s['klinik'], 'Kursi 2');

    test()->actingAs($s['perawat'])->post("/encounters/{$s['kunjungan']}/start", ['chair_id' => $kursi2->id])->assertSessionHasNoErrors();

    tenant()->set($s['klinik']->id);
    $a = AppointmentModel::query()->findOrFail($s['janji']);
    expect($a->status)->toBe('in_progress')->and($a->chair_id)->toBe($kursi2->id);
});

it('RM izin: resepsionis dan kasir tidak melihat kunjungan; pemilik hanya melihat', function () {
    $s = siapKunjungan();

    test()->actingAs($s['user'])->get("/encounters/{$s['kunjungan']}")->assertForbidden();
    test()->actingAs(makeUser(Role::Kasir, $s['klinik']))->get("/encounters/{$s['kunjungan']}")->assertForbidden();
    test()->actingAs(makeUser(Role::Pemilik, $s['klinik']))->get("/encounters/{$s['kunjungan']}")->assertOk()->assertInertia(fn ($p) => $p->where('can.write', false));
    test()->actingAs(makeUser(Role::Admin, $s['klinik']))->put("/encounters/{$s['kunjungan']}/notes", ['plan' => 'x'])->assertForbidden();
});

it('RM kunjungan klinik lain tidak dapat dibuka atau diubah', function () {
    $a = siapKunjungan();
    $b = siapKunjungan();

    test()->actingAs($b['dokter_user'])->get("/encounters/{$a['kunjungan']}")->assertNotFound();
    test()->actingAs($b['dokter_user'])->put("/encounters/{$a['kunjungan']}/notes", ['plan' => 'x'])->assertNotFound();
    test()->actingAs($b['dokter_user'])->post("/encounters/{$a['kunjungan']}/diagnoses", ['diagnosis_id' => icd()])->assertNotFound();
    test()->actingAs($b['dokter_user'])->post("/encounters/{$a['kunjungan']}/close")->assertNotFound();
});

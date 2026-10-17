<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Src\Clinical\Infrastructure\Eloquent\ClinicalDocumentModel;
use Src\IdentityAccess\Domain\Role;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2025-06-16 07:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Kunjungan berjalan dengan dokter ber-SIP, Admin, dan Kasir.
 *
 * @return array<string, mixed>
 */
function siapDokumen(): array
{
    $s = siapKunjungan();
    DB::table('doctors')->where('id', $s['dokter']->id)->update(['sip_no' => 'SIP-123/2024']);
    $s['admin'] = makeUser(Role::Admin, $s['klinik']);
    $s['kasir'] = makeUser(Role::Kasir, $s['klinik']);

    return $s;
}

const ISI_SURAT = 'Pasien Budi Santoso menjalani perawatan gigi dan disarankan istirahat satu hari.';

function terbitkan(array $s, string $jenis, array $o = [], ?object $user = null)
{
    tenant()->set($s['klinik']->id);

    return test()->actingAs($user ?? $s['dokter_user'])->post("/patients/{$s['pasien']}/documents", [
        'type' => $jenis,
        'doctor_id' => $s['dokter']->id,
        'body' => ISI_SURAT,
        ...$o,
    ]);
}

it('RM-10 dokter menerbitkan surat keterangan bernomor SKD dan menyimpan salinan teks akhir beserta SIP dokter', function () {
    $s = siapDokumen();

    terbitkan($s, 'certificate', ['title' => 'Surat sakit', 'encounter_id' => $s['kunjungan']])->assertSessionHasNoErrors()->assertRedirect("/patients/{$s['pasien']}/documents");

    $d = DB::table('clinical_documents')->where('patient_id', $s['pasien'])->first();
    expect($d->type)->toBe('certificate')->and($d->number)->toBe('001/SKD/VI/2025')->and($d->title)->toBe('Surat sakit')
        ->and($d->body)->toBe(ISI_SURAT)->and($d->doctor_name)->toBe('drg. Sari')->and($d->doctor_sip)->toBe('SIP-123/2024')
        ->and($d->encounter_id)->toBe($s['kunjungan'])->and($d->issued_by)->toBe($s['dokter_user']->id)
        ->and(DB::table('audit_logs')->where('subject_id', $d->id)->where('action', 'document.issued')->exists())->toBeTrue();
});

it('RM-09 dan RM-10 nomor berurutan per jenis dan tahun, dengan awalan IC, SKD, RJK', function () {
    $s = siapDokumen();

    terbitkan($s, 'consent')->assertSessionHasNoErrors();
    terbitkan($s, 'consent')->assertSessionHasNoErrors();
    terbitkan($s, 'certificate')->assertSessionHasNoErrors();
    terbitkan($s, 'referral')->assertSessionHasNoErrors();
    expect(DB::table('clinical_documents')->orderBy('number')->pluck('number')->all())->toBe(['001/IC/VI/2025', '001/RJK/VI/2025', '001/SKD/VI/2025', '002/IC/VI/2025']);
});

it('RM-09 nomor kembali ke 0001 pada tahun (zona waktu klinik) yang baru', function () {
    $s = siapDokumen();
    // Lompatan jam melewati batas tidak aktif sesi; sesi dimulai ulang agar tes memeriksa penomoran, bukan masa sesi.
    Carbon::setTestNow(Carbon::parse('2025-12-31 23:55:00', 'Asia/Jakarta'));
    test()->flushSession();
    terbitkan($s, 'consent')->assertSessionHasNoErrors();
    Carbon::setTestNow(Carbon::parse('2026-01-01 00:05:00', 'Asia/Jakarta'));
    terbitkan($s, 'consent')->assertSessionHasNoErrors();

    expect(DB::table('clinical_documents')->orderBy('issued_at')->pluck('number')->all())->toBe(['001/IC/XII/2025', '001/IC/I/2026']);
});

it('RM-09 isi dengan penanda {{...}} yang belum terisi ditolak dan tidak menghabiskan nomor', function () {
    $s = siapDokumen();

    terbitkan($s, 'consent', ['body' => 'Saya menyetujui tindakan {{tindakan}} yang dijelaskan dokter kepada saya.'])->assertSessionHas('error');
    terbitkan($s, 'consent', ['body' => 'terlalu pendek'])->assertSessionHas('error');
    expect(DB::table('clinical_documents')->count())->toBe(0);

    terbitkan($s, 'consent')->assertSessionHasNoErrors();
    expect(DB::table('clinical_documents')->value('number'))->toBe('001/IC/VI/2025');
});

it('RM-09 validasi: jenis, dokter, dan isi wajib; dokter nonaktif, kunjungan pasien lain, dan templat jenis lain ditolak', function () {
    $s = siapDokumen();
    tenant()->set($s['klinik']->id);

    test()->actingAs($s['dokter_user'])->post("/patients/{$s['pasien']}/documents", [])->assertSessionHasErrors(['type', 'doctor_id', 'body']);
    test()->actingAs($s['dokter_user'])->post("/patients/{$s['pasien']}/documents", ['type' => 'ngawur', 'doctor_id' => $s['dokter']->id, 'body' => ISI_SURAT])->assertSessionHasErrors('type');

    $nonaktif = makeDoctor($s['klinik'], 'drg. Lama', [], false);
    terbitkan($s, 'certificate', ['doctor_id' => $nonaktif->id])->assertSessionHas('error');

    $lain = makePatient($s['klinik'], 'Siti Aminah');
    test()->actingAs($s['dokter_user'])->post("/patients/{$lain}/documents", ['type' => 'certificate', 'doctor_id' => $s['dokter']->id, 'body' => ISI_SURAT, 'encounter_id' => $s['kunjungan']])->assertSessionHas('error');

    expect(DB::table('clinical_documents')->count())->toBe(0);
});

it('RM-09 templat Admin muncul di formulir bersama templat bawaan dan hanya yang sesuai jenis', function () {
    $s = siapDokumen();
    tenant()->set($s['klinik']->id);
    test()->actingAs($s['admin'])->post('/settings/master/document-templates', [
        'type' => 'consent', 'name' => 'Consent cabut gigi', 'body' => 'Saya {{pasien}} setuju dilakukan {{tindakan}} oleh {{dokter}}.',
    ])->assertSessionHasNoErrors();
    test()->actingAs($s['admin'])->post('/settings/master/document-templates', [
        'type' => 'referral', 'name' => 'Rujuk bedah mulut', 'body' => 'Mohon penanganan {{pasien}} di {{tujuan}}, alasan {{alasan}}.',
    ])->assertSessionHasNoErrors();

    test()->actingAs($s['dokter_user'])->get("/patients/{$s['pasien']}/documents/create?type=consent")->assertOk()->assertInertia(function ($p) {
        $p->component('Documents/Form')->where('type.value', 'consent')->has('templates', 2)
            ->where('templates.0.name', 'Templat bawaan')->where('templates.1.name', 'Consent cabut gigi')
            ->where('patient.name', 'Budi Santoso')->where('today', '2025-06-16')->has('doctors', 1)->has('encounters', 1);

        return $p;
    });
    test()->actingAs($s['dokter_user'])->get("/patients/{$s['pasien']}/documents/create?type=certificate")->assertInertia(fn ($p) => $p->has('templates', 1));
});

it('RM-09 templat dengan penanda tidak dikenal ditolak saat disimpan, hanya Admin yang dapat membuatnya', function () {
    $s = siapDokumen();
    tenant()->set($s['klinik']->id);
    $data = ['type' => 'certificate', 'name' => 'Salah ketik', 'body' => 'Menerangkan bahwa {{pasienn}} telah diperiksa di klinik ini.'];

    test()->actingAs($s['admin'])->post('/settings/master/document-templates', $data)->assertSessionHasErrors('body');
    test()->actingAs($s['admin'])->post('/settings/master/document-templates', [...$data, 'body' => 'Menerangkan bahwa {{Pasien}} telah diperiksa di klinik ini.'])->assertSessionHasErrors('body');
    test()->actingAs($s['dokter_user'])->post('/settings/master/document-templates', [...$data, 'body' => 'Menerangkan bahwa {{pasien}} telah diperiksa.'])->assertForbidden();
    expect(DB::table('document_templates')->count())->toBe(0);
});

it('RM-09 menerbitkan dari templat Admin menyimpan template_id; templat jenis lain ditolak', function () {
    $s = siapDokumen();
    tenant()->set($s['klinik']->id);
    test()->actingAs($s['admin'])->post('/settings/master/document-templates', [
        'type' => 'consent', 'name' => 'Consent umum', 'body' => 'Saya {{pasien}} setuju dilakukan {{tindakan}} oleh {{dokter}}.',
    ])->assertSessionHasNoErrors();
    $templat = DB::table('document_templates')->value('id');

    terbitkan($s, 'consent', ['template_id' => $templat])->assertSessionHasNoErrors();
    terbitkan($s, 'certificate', ['template_id' => $templat])->assertSessionHas('error');

    expect(DB::table('clinical_documents')->count())->toBe(1)->and(DB::table('clinical_documents')->value('template_id'))->toBe($templat);
});

it('RM-09 dan RM-10 izin per jenis: Perawat hanya consent, Dokter semuanya, Kasir tidak ada', function () {
    $s = siapDokumen();

    terbitkan($s, 'consent', [], $s['perawat'])->assertSessionHasNoErrors();
    terbitkan($s, 'certificate', [], $s['perawat'])->assertForbidden();
    terbitkan($s, 'referral', [], $s['perawat'])->assertForbidden();
    terbitkan($s, 'referral', [], $s['dokter_user'])->assertSessionHasNoErrors();
    terbitkan($s, 'consent', [], $s['kasir'])->assertForbidden();
    test()->actingAs($s['perawat'])->get("/patients/{$s['pasien']}/documents/create?type=certificate")->assertForbidden();
    test()->actingAs($s['perawat'])->get("/patients/{$s['pasien']}/documents/create?type=consent")->assertOk();
    test()->actingAs($s['kasir'])->get("/patients/{$s['pasien']}/documents")->assertForbidden();
    test()->actingAs($s['dokter_user'])->get("/patients/{$s['pasien']}/documents/create?type=ngawur")->assertNotFound();

    test()->actingAs($s['perawat'])->get("/patients/{$s['pasien']}/documents")->assertInertia(fn ($p) => $p->where('can.consent', true)->where('can.letter', false)->has('documents', 2));
    expect(DB::table('clinical_documents')->count())->toBe(2);
});

it('RM-09 daftar dokumen mencatat pembukaan rekam medis dan menampilkan waktu terbit lokal', function () {
    $s = siapDokumen();
    terbitkan($s, 'certificate');

    test()->actingAs($s['dokter_user'])->get("/patients/{$s['pasien']}/documents")->assertOk()->assertInertia(function ($p) {
        $p->component('Documents/Index')->where('patient.name', 'Budi Santoso')->has('documents', 1)
            ->where('documents.0.number', '001/SKD/VI/2025')->where('documents.0.type_label', 'Surat keterangan')->where('documents.0.issued_at', '2025-06-16 07:00');

        return $p;
    });
    expect(DB::table('access_logs')->where('patient_id', $s['pasien'])->where('route', 'documents.index')->exists())->toBeTrue();
});

it('RM-09 cetak PDF informed consent memuat kolom tanda tangan, tercatat di access log dan audit', function () {
    $s = siapDokumen();
    terbitkan($s, 'consent')->assertSessionHasNoErrors();
    $id = DB::table('clinical_documents')->value('id');

    $respons = test()->actingAs($s['dokter_user'])->get("/documents/{$id}/print");

    $respons->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(str_starts_with($respons->getContent(), '%PDF'))->toBeTrue()
        ->and((string) $respons->headers->get('Content-Disposition'))->toContain('inline')->toContain('001-ic-vi-2025.pdf')
        ->and(DB::table('access_logs')->where('patient_id', $s['pasien'])->where('route', 'documents.print')->exists())->toBeTrue()
        ->and(DB::table('audit_logs')->where('subject_id', $id)->where('action', 'document.printed')->exists())->toBeTrue();

    test()->actingAs($s['dokter_user'])->get("/documents/{$id}/print?download=1")->assertHeader('Content-Disposition', 'attachment; filename="001-ic-vi-2025.pdf"');
});

it('RM-10 cetak ulang memakai salinan tersimpan: ganti nama dokter atau templat tidak mengubah isi', function () {
    $s = siapDokumen();
    terbitkan($s, 'certificate')->assertSessionHasNoErrors();
    $id = DB::table('clinical_documents')->value('id');
    DB::table('doctors')->where('id', $s['dokter']->id)->update(['name' => 'drg. Baru', 'sip_no' => 'SIP-999']);

    test()->actingAs($s['dokter_user'])->get("/documents/{$id}/print")->assertOk();

    $d = DB::table('clinical_documents')->where('id', $id)->first();
    expect($d->doctor_name)->toBe('drg. Sari')->and($d->doctor_sip)->toBe('SIP-123/2024')->and($d->body)->toBe(ISI_SURAT);
});

it('RM-09 dokumen hanya-tambah: ubah atau hapus lewat model ditolak', function () {
    $s = siapDokumen();
    terbitkan($s, 'consent')->assertSessionHasNoErrors();
    tenant()->set($s['klinik']->id);
    $model = ClinicalDocumentModel::query()->firstOrFail();

    expect(function () use ($model) {
        $model->body = 'diubah';
        $model->save();
    })->toThrow(LogicException::class);
    expect(fn () => $model->delete())->toThrow(LogicException::class);
    expect(DB::table('clinical_documents')->value('body'))->toBe(ISI_SURAT);
});

it('RM-09 dokumen klinik lain tidak dapat dicetak dan pasien klinik lain tidak dapat diberi dokumen', function () {
    $s = siapDokumen();
    terbitkan($s, 'consent')->assertSessionHasNoErrors();
    $id = DB::table('clinical_documents')->value('id');
    $lain = makeClinic('Klinik Lain');
    $dokterLain = makeUser(Role::Dokter, $lain);
    $pasienLain = makePatient($lain, 'Pasien Lain');
    tenant()->set($lain->id);

    test()->actingAs($dokterLain)->get("/documents/{$id}/print")->assertStatus(404);
    test()->actingAs($dokterLain)->get("/patients/{$s['pasien']}/documents")->assertStatus(404);
    test()->actingAs($dokterLain)->post("/patients/{$s['pasien']}/documents", ['type' => 'consent', 'doctor_id' => $s['dokter']->id, 'body' => ISI_SURAT])->assertStatus(404);
    test()->actingAs($dokterLain)->get("/patients/{$pasienLain}/documents")->assertOk()->assertInertia(fn ($p) => $p->has('documents', 0));
    expect(DB::table('clinical_documents')->count())->toBe(1);
});

it('PAS-07 penggabungan pasien memindahkan dokumen klinis ke pasien tujuan', function () {
    $s = siapDokumen();
    terbitkan($s, 'consent')->assertSessionHasNoErrors();
    $tujuan = makePatient($s['klinik'], 'Budi S.');
    tenant()->set($s['klinik']->id);

    test()->actingAs($s['admin'])->post("/patients/{$tujuan}/merge", ['source_id' => $s['pasien'], 'reason' => 'Pasien terdaftar dua kali', 'confirm' => true])->assertSessionHasNoErrors();

    expect(DB::table('clinical_documents')->value('patient_id'))->toBe($tujuan);
});

it('ADR-0008 kop dokumen klinis sama dengan kop semua cetakan klinik', function () {
    $s = siapDokumen();
    $kop = isiKop($s['klinik']);
    terbitkan($s, 'consent')->assertSessionHasNoErrors();
    $id = DB::table('clinical_documents')->value('id');
    rekamPdf();

    test()->actingAs($s['dokter_user'])->get("/documents/{$id}/print")->assertOk();

    expect(PdfPerekam::$permintaan)->toHaveCount(1)->and(PdfPerekam::$permintaan[0]->data['letterhead'])->toBe($kop)
        ->and(PdfPerekam::$permintaan[0]->data['place'])->toBe('Surakarta');
});

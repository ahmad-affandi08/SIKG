<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Src\Clinical\Domain\InvalidClinicalData;
use Src\Clinical\Domain\SignatureImage;
use Src\Clinical\Infrastructure\Eloquent\DocumentSignatureModel;
use Src\IdentityAccess\Domain\Role;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2025-06-16 07:00:00', 'Asia/Jakarta'));
    Storage::fake('private');
});

afterEach(function () {
    Carbon::setTestNow();
});

/** Gambar tanda tangan uji: PNG bergoresan pada latar transparan, sebagai data URL. */
function ttdUji(int $lebar = 300, int $tinggi = 100): string
{
    $gambar = imagecreatetruecolor($lebar, $tinggi);
    imagesavealpha($gambar, true);
    imagefill($gambar, 0, 0, (int) imagecolorallocatealpha($gambar, 0, 0, 0, 127));
    imagesetthickness($gambar, 3);
    imageline($gambar, 10, 80, 120, 20, (int) imagecolorallocate($gambar, 0, 0, 0));
    imageline($gambar, 120, 20, 250, 70, (int) imagecolorallocate($gambar, 0, 0, 0));
    ob_start();
    imagepng($gambar);

    return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
}

/**
 * Kunjungan berjalan, dokter ber-SIP, dan informed consent yang sudah terbit.
 *
 * @return array<string, mixed>
 */
function siapTtd(): array
{
    $s = siapKunjungan();
    $s['admin'] = makeUser(Role::Admin, $s['klinik']);
    $s['kasir'] = makeUser(Role::Kasir, $s['klinik']);
    tenant()->set($s['klinik']->id);
    test()->actingAs($s['dokter_user'])->post("/patients/{$s['pasien']}/documents", [
        'type' => 'consent', 'doctor_id' => $s['dokter']->id, 'encounter_id' => $s['kunjungan'],
        'body' => "Tindakan: Pencabutan gigi 48\nRisiko: nyeri, bengkak, dan perdarahan setelah tindakan.",
    ])->assertSessionHasNoErrors();
    $s['dokumen'] = (string) DB::table('clinical_documents')->where('type', 'consent')->value('id');

    return $s;
}

/** @param array<string, mixed> $o */
function tandatangani(array $s, array $o = [])
{
    return test()->actingAs($s['dokter_user'])->post("/documents/{$s['dokumen']}/sign", [
        'signer_name' => 'Budi Santoso', 'signer_role' => 'patient', 'signature' => ttdUji(), ...$o,
    ]);
}

it('P2-07 gambar tanda tangan divalidasi: PNG sah, ukuran wajar, bukan tipe lain', function () {
    expect(SignatureImage::fromDataUrl(ttdUji())->dataUrl())->toStartWith('data:image/png;base64,');

    foreach ([
        'bukan-data-url',
        'data:image/jpeg;base64,'.base64_encode('x'),
        'data:image/png;base64,###',
        'data:image/png;base64,'.base64_encode('PNG palsu tanpa tanda yang benar, cukup panjang untuk diperiksa'),
        ttdUji(10, 10),
        ttdUji(3000, 100),
    ] as $buruk) {
        expect(fn () => SignatureImage::fromDataUrl($buruk))->toThrow(InvalidClinicalData::class);
    }
});

it('P2-07 pasien menandatangani informed consent di tablet dan PDF bertanda tangan tersimpan di rekam medis', function () {
    config(['sikg.encrypt_files' => true]);
    $s = siapTtd();

    $this->actingAs($s['dokter_user'])->get("/documents/{$s['dokumen']}/sign")->assertOk()->assertInertia(fn ($p) => $p->component('Documents/Sign')
        ->where('document.signable', true)->where('document.signed', false)->where('document.number', '001/IC/VI/2025'));

    tandatangani($s, ['witness_name' => 'Sari Wulandari', 'witness_signature' => ttdUji()])->assertSessionHasNoErrors()->assertRedirect("/patients/{$s['pasien']}/documents");

    tenant()->set($s['klinik']->id);
    $ttd = DB::table('document_signatures')->first();
    expect($ttd->document_id)->toBe($s['dokumen'])->and($ttd->signer_name)->toBe('Budi Santoso')->and($ttd->signer_role)->toBe('patient')->and($ttd->witness_name)->toBe('Sari Wulandari')
        ->and($ttd->signed_by)->toBe($s['dokter_user']->id)->and((bool) $ttd->pdf_encrypted)->toBeTrue()
        ->and($ttd->pdf_path)->toStartWith("clinical/{$s['klinik']->id}/{$s['pasien']}/")->and($ttd->pdf_path)->toEndWith('.pdf')
        ->and($ttd->body_sha256)->toBe(hash('sha256', (string) DB::table('clinical_documents')->where('id', $s['dokumen'])->value('body')));
    Storage::disk('private')->assertExists($ttd->pdf_path);
    expect(str_starts_with(Storage::disk('private')->get($ttd->pdf_path), '%PDF'))->toBeFalse();

    $respons = $this->get("/documents/{$s['dokumen']}/signed")->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $isi = $respons->getContent();
    expect(str_starts_with((string) $isi, '%PDF'))->toBeTrue()->and(hash('sha256', (string) $isi))->toBe($ttd->pdf_sha256)
        ->and(DB::table('audit_logs')->where('action', 'document.signed')->count())->toBe(1)
        ->and(DB::table('audit_logs')->where('action', 'document.signed_downloaded')->count())->toBe(1)
        ->and(DB::table('access_logs')->where('route', 'documents.signed')->count())->toBe(1);
});

it('P2-07 daftar dokumen menandai yang sudah ditandatangani beserta penanda tangannya', function () {
    $s = siapTtd();
    tandatangani($s, ['signer_role' => 'guardian', 'signer_name' => 'Ibu Budi'])->assertSessionHasNoErrors();

    $this->get("/patients/{$s['pasien']}/documents")->assertInertia(fn ($p) => $p->component('Documents/Index')->where('documents.0.signable', true)->where('documents.0.signed', true)
        ->where('documents.0.signer_name', 'Ibu Budi')->where('documents.0.signed_at', '2025-06-16 07:00'));
});

it('P2-07 dokumen hanya dapat ditandatangani sekali dan isinya tidak berubah', function () {
    $s = siapTtd();
    tandatangani($s)->assertSessionHasNoErrors();

    tandatangani($s, ['signer_name' => 'Orang Lain'])->assertSessionHas('error_code', 'signature.already_signed');
    expect(DB::table('document_signatures')->count())->toBe(1)->and(DB::table('document_signatures')->value('signer_name'))->toBe('Budi Santoso');
});

it('P2-07 masukan yang salah ditolak tanpa menyimpan apa pun', function () {
    $s = siapTtd();

    tandatangani($s, ['signer_name' => ''])->assertSessionHasErrors('signer_name');
    tandatangani($s, ['signer_role' => 'dokter'])->assertSessionHasErrors('signer_role');
    tandatangani($s, ['signature' => ''])->assertSessionHasErrors('signature');
    tandatangani($s, ['signature' => 'data:image/png;base64,AAAA'])->assertSessionHas('error_code', 'signature.invalid');
    tandatangani($s, ['witness_name' => 'Sari'])->assertSessionHas('error_code', 'signature.witness_incomplete');
    tandatangani($s, ['witness_signature' => ttdUji()])->assertSessionHas('error_code', 'signature.witness_incomplete');
    expect(DB::table('document_signatures')->count())->toBe(0);
});

it('P2-07 surat keterangan dan rujukan tidak ditandatangani di layar', function () {
    $s = siapTtd();
    $this->actingAs($s['dokter_user'])->post("/patients/{$s['pasien']}/documents", [
        'type' => 'certificate', 'doctor_id' => $s['dokter']->id, 'body' => 'Pasien Budi Santoso menjalani perawatan gigi dan disarankan istirahat satu hari.',
    ])->assertSessionHasNoErrors();
    $surat = (string) DB::table('clinical_documents')->where('type', 'certificate')->value('id');

    $this->post("/documents/{$surat}/sign", ['signer_name' => 'Budi', 'signer_role' => 'patient', 'signature' => ttdUji()])->assertSessionHas('error_code', 'signature.not_signable');
    $this->get("/documents/{$surat}/sign")->assertInertia(fn ($p) => $p->where('document.signable', false));
    expect(DB::table('document_signatures')->count())->toBe(0);
});

it('P2-07 PDF bertanda tangan tidak tersedia sebelum ditandatangani dan hak akses dijaga', function () {
    $s = siapTtd();

    $this->actingAs($s['dokter_user'])->get("/documents/{$s['dokumen']}/signed")->assertSessionHas('error_code', 'signature.not_signed');

    $this->actingAs($s['kasir'])->get("/documents/{$s['dokumen']}/sign")->assertForbidden();
    $this->post("/documents/{$s['dokumen']}/sign", ['signer_name' => 'Budi', 'signer_role' => 'patient', 'signature' => ttdUji()])->assertForbidden();
    $this->get("/documents/{$s['dokumen']}/signed")->assertForbidden();
    expect(DB::table('document_signatures')->count())->toBe(0);
});

it('P2-07 klinik lain tidak dapat menandatangani atau membuka dokumen klinik ini', function () {
    $s = siapTtd();
    tandatangani($s)->assertSessionHasNoErrors();
    $lain = siapKunjungan();

    $this->actingAs($lain['dokter_user'])->get("/documents/{$s['dokumen']}/signed")->assertNotFound();
    $this->post("/documents/{$s['dokumen']}/sign", ['signer_name' => 'X', 'signer_role' => 'patient', 'signature' => ttdUji()])->assertNotFound();
});

it('P2-07 bukti tanda tangan tidak dapat diubah atau dihapus lewat aplikasi', function () {
    $s = siapTtd();
    tandatangani($s)->assertSessionHasNoErrors();
    tenant()->set($s['klinik']->id);

    $model = DocumentSignatureModel::query()->firstOrFail();
    expect(fn () => $model->forceFill(['signer_name' => 'Palsu'])->save())->toThrow(LogicException::class)
        ->and(fn () => $model->delete())->toThrow(LogicException::class);
});

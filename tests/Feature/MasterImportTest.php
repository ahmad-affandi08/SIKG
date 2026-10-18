<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Src\IdentityAccess\Domain\Role;

/**
 * @param  list<list<string|int|float|null>>  $rows  baris pertama = judul kolom
 */
function xlsxImpor(array $rows): UploadedFile
{
    $book = new Spreadsheet;
    $book->getActiveSheet()->fromArray($rows);
    $stream = fopen('php://temp', 'r+');
    (new Xlsx($book))->save($stream);
    rewind($stream);

    return UploadedFile::fake()->createWithContent('impor.xlsx', (string) stream_get_contents($stream));
}

function tokenDariPengalihan($respons): string
{
    $lokasi = (string) $respons->headers->get('Location');

    return substr($lokasi, strrpos($lokasi, '/') + 1);
}

const JUDUL_TINDAKAN = ['Kode', 'Nama tindakan', 'Kategori', 'Cakupan', 'Tarif standar', 'Kondisi gigi hasil tindakan', 'Kode ICD-9-CM (SATUSEHAT)'];

it('MST-09 templat impor dapat diunduh dan memuat judul kolom serta lembar petunjuk', function () {
    $admin = makeUser(Role::Admin);

    $respons = $this->actingAs($admin)->get('/settings/master/procedures/import/template');

    $respons->assertOk();
    expect((string) $respons->headers->get('Content-Disposition'))->toContain('templat-impor-procedures.xlsx');
    $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
    file_put_contents($path, $respons->getContent());
    $book = IOFactory::load($path);
    unlink($path);
    expect($book->getSheetByName('Data')?->getCell('A1')->getValue())->toBe('Kode')
        ->and($book->getSheetByName('Petunjuk'))->not->toBeNull();
});

it('MST-09 pratinjau menghitung baris sah, sudah ada, dan galat; konfirmasi hanya membuat yang sah', function () {
    $admin = makeUser(Role::Admin);
    $klinik = $admin->clinic_id;
    tenant()->set($klinik);
    DB::table('procedures')->insert([
        'id' => strtoupper((string) Str::ulid()), 'clinic_id' => $klinik, 'code' => 'ADA', 'name' => 'Sudah ada',
        'scope' => 'visit', 'default_price_amount' => 1000, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $respons = $this->actingAs($admin)->post('/settings/master/procedures/import', ['file' => xlsxImpor([
        JUDUL_TINDAKAN,
        ['SCL1', 'Scaling', 'Periodonsia', 'Per rahang', 'Rp 250.000', null, '96.54'],
        ['TMB1', 'Tambal komposit', 'Konservasi', 'surface', 350000, null, '23.2'],
        ['ADA', 'Bentrok kode lama', null, 'visit', 1000, null, null],
        ['SCL1', 'Kode ganda di berkas', null, 'jaw', 1000, null, null],
        ['BAD1', 'Cakupan salah', null, 'tidak-ada', 1000, null, null],
        ['BAD2', 'Tarif pecahan', null, 'jaw', '1000,5', null, null],
        [null, null, null, null, null, null, null],
    ])]);
    $respons->assertRedirect();
    $token = tokenDariPengalihan($respons);

    $this->actingAs($admin)->get("/settings/master/procedures/import/{$token}")->assertOk()->assertInertia(fn ($halaman) => $halaman
        ->component('Settings/Master/Import')
        ->where('preview.total_rows', 6)
        ->where('preview.valid_rows', 2)
        ->where('preview.existing_count', 1)
        ->where('preview.error_rows', 3));

    $this->actingAs($admin)->post("/settings/master/procedures/import/{$token}/commit")
        ->assertRedirect('/settings/master/procedures')->assertSessionHas('success', '2 baris diimpor.');

    tenant()->set($klinik);
    $kode = DB::table('procedures')->where('clinic_id', $klinik)->orderBy('code')->pluck('code')->all();
    $scaling = DB::table('procedures')->where('clinic_id', $klinik)->where('code', 'SCL1')->first();
    expect($kode)->toBe(['ADA', 'SCL1', 'TMB1'])
        ->and((int) $scaling->default_price_amount)->toBe(250000)
        ->and($scaling->scope)->toBe('jaw')
        ->and((bool) $scaling->is_active)->toBeTrue()
        ->and(DB::table('audit_logs')->where('action', 'procedure.created')->count())->toBe(2)
        ->and(DB::table('audit_logs')->where('action', 'master.imported')->count())->toBe(1);
});

it('MST-09 konfirmasi kedua dengan token yang sama ditolak', function () {
    $admin = makeUser(Role::Admin);
    $respons = $this->actingAs($admin)->post('/settings/master/procedures/import', ['file' => xlsxImpor([
        JUDUL_TINDAKAN, ['ONE', 'Satu', null, 'visit', 100, null, null],
    ])]);
    $token = tokenDariPengalihan($respons);

    $this->actingAs($admin)->post("/settings/master/procedures/import/{$token}/commit")->assertSessionHas('success');
    $this->actingAs($admin)->post("/settings/master/procedures/import/{$token}/commit")
        ->assertRedirect('/settings/master/procedures/import')->assertSessionHas('error');

    tenant()->set($admin->clinic_id);
    expect(DB::table('procedures')->where('code', 'ONE')->count())->toBe(1);
});

it('MST-09 token pratinjau milik pengguna lain atau klinik lain tidak dapat dikonfirmasi', function () {
    $admin = makeUser(Role::Admin);
    $lain = makeUser(Role::Admin, $admin->clinic_id ? null : null);
    $token = tokenDariPengalihan($this->actingAs($admin)->post('/settings/master/procedures/import', ['file' => xlsxImpor([
        JUDUL_TINDAKAN, ['ONE', 'Satu', null, 'visit', 100, null, null],
    ])]));

    $this->actingAs($lain)->post("/settings/master/procedures/import/{$token}/commit")->assertSessionHas('error');
    $this->actingAs($lain)->get("/settings/master/procedures/import/{$token}")->assertNotFound();

    tenant()->set($admin->clinic_id);
    expect(DB::table('procedures')->where('code', 'ONE')->count())->toBe(0);
});

it('MST-09 impor obat menerima format rupiah dan memetakan kolom lewat label', function () {
    $admin = makeUser(Role::Admin);
    $respons = $this->actingAs($admin)->post('/settings/master/medicines/import', ['file' => xlsxImpor([
        ['Nama obat atau bahan', 'Satuan', 'Harga jual', 'Stok minimum', 'Kelas obat'],
        ['Amoksisilin 500 mg', 'kapsul', 'Rp 2.000', 20, 'penisilin'],
        ['Parasetamol 500 mg', 'tablet', 1000, 50, null],
    ])]);

    $this->actingAs($admin)->post('/settings/master/medicines/import/'.tokenDariPengalihan($respons).'/commit')->assertSessionHas('success');

    tenant()->set($admin->clinic_id);
    $amox = DB::table('medicines')->where('name', 'Amoksisilin 500 mg')->first();
    expect((int) $amox->sell_price_amount)->toBe(2000)->and($amox->drug_class)->toBe('penisilin')->and((int) $amox->min_stock)->toBe(20);
});

it('MST-09 berkas CSV bertitik koma dapat dibaca', function () {
    $admin = makeUser(Role::Admin);
    $csv = "Nama pemasok;Nama kontak;Telepon\nPT Dental Jaya;Budi;0812\nCV Medika;;\n";
    $respons = $this->actingAs($admin)->post('/settings/master/suppliers/import', ['file' => UploadedFile::fake()->createWithContent('pemasok.csv', $csv)]);

    $this->actingAs($admin)->post('/settings/master/suppliers/import/'.tokenDariPengalihan($respons).'/commit')->assertSessionHas('success', '2 baris diimpor.');

    tenant()->set($admin->clinic_id);
    expect(DB::table('suppliers')->orderBy('name')->pluck('name')->all())->toBe(['CV Medika', 'PT Dental Jaya']);
});

it('MST-09 menolak berkas tanpa kolom wajib, kosong, atau melebihi batas baris', function () {
    $admin = makeUser(Role::Admin);

    $this->actingAs($admin)->post('/settings/master/procedures/import', ['file' => xlsxImpor([['Kode', 'Nama tindakan'], ['A', 'B']])])
        ->assertSessionHasErrors('file');
    $this->actingAs($admin)->post('/settings/master/procedures/import', ['file' => xlsxImpor([JUDUL_TINDAKAN])])
        ->assertSessionHasErrors('file');

    $banyak = [JUDUL_TINDAKAN];
    for ($i = 1; $i <= 1001; $i++) {
        $banyak[] = ["K{$i}", "Tindakan {$i}", null, 'visit', 100, null, null];
    }
    $this->actingAs($admin)->post('/settings/master/procedures/import', ['file' => xlsxImpor($banyak)])->assertSessionHasErrors('file');

    tenant()->set($admin->clinic_id);
    expect(DB::table('procedures')->count())->toBe(0);
});

it('MST-09 hanya pemegang izin kelola yang dapat mengimpor dan hanya entitas yang mengizinkannya', function () {
    $klinik = makeClinic();
    $kasir = makeUser(Role::Kasir, $klinik);
    $admin = makeUser(Role::Admin, $klinik);
    $berkas = xlsxImpor([JUDUL_TINDAKAN, ['ONE', 'Satu', null, 'visit', 100, null, null]]);

    $this->actingAs($kasir)->post('/settings/master/procedures/import', ['file' => $berkas])->assertForbidden();
    $this->actingAs($kasir)->get('/settings/master/procedures/import/template')->assertForbidden();
    expect($this->actingAs($admin)->get('/settings/master/chairs/import')->status())->toBeIn([404, 405]);
});

it('MST-09 daftar master memberi tahu entitas mana yang dapat diimpor', function () {
    $admin = makeUser(Role::Admin);

    $this->actingAs($admin)->get('/settings/master/procedures')->assertInertia(fn ($halaman) => $halaman->where('importable', true));
    $this->actingAs($admin)->get('/settings/master/chairs')->assertInertia(fn ($halaman) => $halaman->where('importable', false));
});

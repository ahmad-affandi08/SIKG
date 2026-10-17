<?php

declare(strict_types=1);

use Src\Clinic\Infrastructure\Eloquent\ChairModel;
use Src\Clinic\Infrastructure\Eloquent\PaymentMethodModel;
use Src\Clinic\Infrastructure\Eloquent\ProcedureModel;
use Src\IdentityAccess\Domain\Role;
use Src\Pharmacy\Infrastructure\Eloquent\MedicineModel;
use Src\Shared\Infrastructure\Audit\AuditLogModel;

it('MST-03 Admin menambah kursi dan perubahan tercatat di audit', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);

    $this->actingAs($admin)->post('/settings/master/chairs', ['name' => 'Kursi 1'])
        ->assertSessionHasNoErrors()->assertRedirect();

    tenant()->set($klinik->id);
    $kursi = ChairModel::query()->firstOrFail();
    expect($kursi->name)->toBe('Kursi 1')->and($kursi->is_active)->toBeTrue();
    expect(AuditLogModel::query()->where('action', 'chair.created')->where('subject_id', $kursi->id)->exists())->toBeTrue();
});

it('MST-03 kursi dinonaktifkan tanpa dihapus lalu diaktifkan lagi', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $this->actingAs($admin)->post('/settings/master/chairs', ['name' => 'Kursi 2']);
    tenant()->set($klinik->id);
    $id = ChairModel::query()->firstOrFail()->id;

    $this->actingAs($admin)->post("/settings/master/chairs/{$id}/deactivate")->assertRedirect();
    tenant()->set($klinik->id);
    expect(ChairModel::query()->findOrFail($id)->is_active)->toBeFalse();

    $this->actingAs($admin)->post("/settings/master/chairs/{$id}/activate")->assertRedirect();
    tenant()->set($klinik->id);
    expect(ChairModel::query()->findOrFail($id)->is_active)->toBeTrue()
        ->and(AuditLogModel::query()->whereIn('action', ['chair.deactivated', 'chair.activated'])->count())->toBe(2);
});

it('MST-04 katalog tindakan menolak kode ganda dalam satu klinik dan tarif desimal', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $data = ['code' => 'TMB-01', 'name' => 'Tambal komposit', 'category' => 'Konservasi', 'scope' => 'surface', 'default_price_amount' => 250000];

    $this->actingAs($admin)->post('/settings/master/procedures', $data)->assertSessionHasNoErrors();
    $this->actingAs($admin)->post('/settings/master/procedures', $data)->assertSessionHasErrors('code');
    $this->actingAs($admin)->post('/settings/master/procedures', [...$data, 'code' => 'TMB-02', 'default_price_amount' => '1500.50'])
        ->assertSessionHasErrors('default_price_amount');
    $this->actingAs($admin)->post('/settings/master/procedures', [...$data, 'code' => 'TMB-03', 'scope' => 'ngawur'])
        ->assertSessionHasErrors('scope');

    tenant()->set($klinik->id);
    expect(ProcedureModel::query()->count())->toBe(1);
});

it('MST-04 kode tindakan yang sama boleh dipakai klinik lain', function () {
    $a = makeClinic('A');
    $b = makeClinic('B');
    $data = ['code' => 'CABUT', 'name' => 'Cabut gigi', 'scope' => 'tooth', 'default_price_amount' => 100000];

    $this->actingAs(makeUser(Role::Admin, $a))->post('/settings/master/procedures', $data)->assertSessionHasNoErrors();
    $this->actingAs(makeUser(Role::Admin, $b))->post('/settings/master/procedures', $data)->assertSessionHasNoErrors();
});

it('MST-04 ubah tarif tercatat dengan nilai sebelum dan sesudah', function () {
    $klinik = makeClinic();
    $pemilik = makeUser(Role::Pemilik, $klinik);
    $this->actingAs($pemilik)->post('/settings/master/procedures', ['code' => 'X1', 'name' => 'Scaling', 'scope' => 'visit', 'default_price_amount' => 100000]);
    tenant()->set($klinik->id);
    $id = ProcedureModel::query()->firstOrFail()->id;

    $this->actingAs($pemilik)->put("/settings/master/procedures/{$id}", ['code' => 'X1', 'name' => 'Scaling', 'scope' => 'visit', 'default_price_amount' => 150000])
        ->assertSessionHasNoErrors();

    tenant()->set($klinik->id);
    $log = AuditLogModel::query()->where('action', 'procedure.updated')->firstOrFail();
    expect($log->before['default_price_amount'])->toBe(100000)->and($log->after['default_price_amount'])->toBe(150000);
});

it('MST-05 katalog obat menyimpan harga jual bulat dan kelas obat', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);

    $this->actingAs($admin)->post('/settings/master/medicines', [
        'name' => 'Amoksisilin 500 mg', 'unit' => 'kapsul', 'sell_price_amount' => 2500, 'min_stock' => 20, 'drug_class' => 'penisilin',
    ])->assertSessionHasNoErrors();

    tenant()->set($klinik->id);
    $obat = MedicineModel::query()->firstOrFail();
    expect($obat->sell_price_amount)->toBe(2500)->and($obat->drug_class)->toBe('penisilin');
});

it('MST-06 metode pembayaran hanya menerima jenis yang dikenal', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);

    $this->actingAs($admin)->post('/settings/master/payment-methods', ['name' => 'QRIS Klinik', 'type' => 'qris'])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post('/settings/master/payment-methods', ['name' => 'Kripto', 'type' => 'kripto'])->assertSessionHasErrors('type');

    tenant()->set($klinik->id);
    expect(PaymentMethodModel::query()->count())->toBe(1);
});

it('MST master data hanya menampilkan data klinik sendiri', function () {
    $a = makeClinic('A');
    $b = makeClinic('B');
    $this->actingAs(makeUser(Role::Admin, $a))->post('/settings/master/chairs', ['name' => 'Milik A']);

    $this->actingAs(makeUser(Role::Admin, $b))->get('/settings/master/chairs')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Settings/Master/Index')->where('rows', []));
});

it('MST master data tidak dapat diubah klinik lain', function () {
    $a = makeClinic('A');
    $b = makeClinic('B');
    $this->actingAs(makeUser(Role::Admin, $a))->post('/settings/master/chairs', ['name' => 'Milik A']);
    tenant()->set($a->id);
    $id = ChairModel::query()->firstOrFail()->id;

    $this->actingAs(makeUser(Role::Admin, $b))->post("/settings/master/chairs/{$id}/deactivate")->assertNotFound();
});

it('MST izin: dokter melihat tarif tetapi tidak mengubah; resepsionis tidak melihat kursi', function () {
    $klinik = makeClinic();
    $dokter = makeUser(Role::Dokter, $klinik);
    $resepsionis = makeUser(Role::Resepsionis, $klinik);

    $this->actingAs($dokter)->get('/settings/master/procedures')->assertOk()->assertInertia(fn ($p) => $p->where('canManage', false));
    $this->actingAs($dokter)->post('/settings/master/procedures', ['code' => 'A', 'name' => 'A', 'scope' => 'visit', 'default_price_amount' => 1])->assertForbidden();
    $this->actingAs($resepsionis)->get('/settings/master/chairs')->assertForbidden();
    $this->actingAs($resepsionis)->get('/settings/master/medicines')->assertForbidden();
});

it('MST-08 mengubah tarif standar tidak mengubah salinan tarif yang sudah dibuat', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $this->actingAs($admin)->post('/settings/master/procedures', ['code' => 'T1', 'name' => 'Tambal', 'scope' => 'surface', 'default_price_amount' => 200000]);
    tenant()->set($klinik->id);
    $prosedur = ProcedureModel::query()->firstOrFail();
    $salinan = $prosedur->default_price_amount;

    $this->actingAs($admin)->put("/settings/master/procedures/{$prosedur->id}", ['code' => 'T1', 'name' => 'Tambal', 'scope' => 'surface', 'default_price_amount' => 300000]);

    expect($salinan)->toBe(200000)->and($prosedur->fresh()->default_price_amount)->toBe(300000);
});

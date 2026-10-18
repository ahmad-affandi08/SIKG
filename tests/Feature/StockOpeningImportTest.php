<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\IdentityAccess\Domain\Role;

function obatUjiStok(string $clinicId, string $name, bool $active = true): string
{
    $id = strtoupper((string) Str::ulid());
    DB::table('medicines')->insert([
        'id' => $id, 'clinic_id' => $clinicId, 'name' => $name, 'unit' => 'tablet', 'sell_price_amount' => 1000, 'min_stock' => 0,
        'drug_class' => null, 'is_active' => $active, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $id;
}

const JUDUL_STOK_AWAL = ['Nama obat atau bahan', 'Jumlah', 'Nomor batch', 'Tanggal kedaluwarsa'];

it('MST-11 impor stok awal menambah stok lewat penerimaan biasa dan mencatat mutasi', function () {
    $admin = makeUser(Role::Admin);
    $obatId = obatUjiStok($admin->clinic_id, 'Amoksisilin 500 mg');

    $respons = $this->actingAs($admin)->post('/settings/master/stock-opening/import', ['file' => xlsxImpor([
        JUDUL_STOK_AWAL,
        ['Amoksisilin 500 mg', 100, 'B-001', '31/12/2030'],
        ['amoksisilin 500 mg', 50, 'B-002', '2031-06-30'],
    ])]);
    $respons->assertRedirect();

    $this->actingAs($admin)->post('/settings/master/stock-opening/import/'.tokenDariPengalihan($respons).'/commit')
        ->assertRedirect('/stock')->assertSessionHas('success', '2 baris diimpor.');

    expect((int) DB::table('stock_balances')->where('medicine_id', $obatId)->sum('quantity'))->toBe(150)
        ->and(DB::table('stock_movements')->where('medicine_id', $obatId)->count())->toBe(2)
        ->and(DB::table('audit_logs')->where('action', 'master.imported')->count())->toBe(1);
});

it('MST-11 obat yang tidak ada atau nonaktif dan jumlah tidak sah ditolak per baris', function () {
    $admin = makeUser(Role::Admin);
    obatUjiStok($admin->clinic_id, 'Obat Nonaktif', false);
    obatUjiStok($admin->clinic_id, 'Obat Aktif');

    $respons = $this->actingAs($admin)->post('/settings/master/stock-opening/import', ['file' => xlsxImpor([
        JUDUL_STOK_AWAL,
        ['Obat Tidak Ada', 10, null, null],
        ['Obat Nonaktif', 10, null, null],
        ['Obat Aktif', '0', null, null],
        ['Obat Aktif', 5, null, null],
    ])]);

    $this->actingAs($admin)->get('/settings/master/stock-opening/import/'.tokenDariPengalihan($respons))
        ->assertInertia(fn ($page) => $page->where('preview.valid_rows', 1)->where('preview.error_rows', 3));
});

it('MST-11 token yang sama tidak dapat diterapkan dua kali sehingga stok tidak berlipat', function () {
    $admin = makeUser(Role::Admin);
    $obatId = obatUjiStok($admin->clinic_id, 'Parasetamol 500 mg');
    $respons = $this->actingAs($admin)->post('/settings/master/stock-opening/import', ['file' => xlsxImpor([
        JUDUL_STOK_AWAL, ['Parasetamol 500 mg', 40, null, null],
    ])]);
    $token = tokenDariPengalihan($respons);

    $this->actingAs($admin)->post("/settings/master/stock-opening/import/{$token}/commit")->assertSessionHas('success');
    $this->actingAs($admin)->post("/settings/master/stock-opening/import/{$token}/commit")->assertSessionHas('error');

    expect((int) DB::table('stock_balances')->where('medicine_id', $obatId)->sum('quantity'))->toBe(40);
});

it('MST-11 hanya pemegang izin kelola stok yang dapat mengimpor stok awal', function () {
    $resepsionis = makeUser(Role::Resepsionis);

    $this->actingAs($resepsionis)->get('/settings/master/stock-opening/import')->assertForbidden();
    $this->actingAs($resepsionis)->post('/settings/master/stock-opening/import', ['file' => xlsxImpor([JUDUL_STOK_AWAL])])->assertForbidden();
});

it('MST-09 angka berpecahan seperti 250000.50 ditolak dan bukan dibaca sebagai 25000050', function () {
    $admin = makeUser(Role::Admin);
    $respons = $this->actingAs($admin)->post('/settings/master/medicines/import', ['file' => xlsxImpor([
        ['Nama obat atau bahan', 'Satuan', 'Harga jual', 'Stok minimum'],
        ['Obat A', 'tablet', '250000.50', '0'],
        ['Obat B', 'tablet', '1.5', '0'],
        ['Obat C', 'tablet', '1.250.000', '0'],
    ])]);

    $this->actingAs($admin)->get('/settings/master/medicines/import/'.tokenDariPengalihan($respons))
        ->assertInertia(fn ($page) => $page->where('preview.valid_rows', 1)->where('preview.error_rows', 2));
});

it('MST-10 banner Penyiapan awal tampil di Beranda bagi Pemilik sampai langkah wajib selesai', function () {
    $pemilik = makeUser(Role::Pemilik);

    $this->actingAs($pemilik)->get('/dashboard')->assertInertia(fn ($page) => $page->has('setup.total')->where('setup.done', 0));

    $resepsionis = makeUser(Role::Resepsionis);
    $this->actingAs($resepsionis)->get('/dashboard')->assertInertia(fn ($page) => $page->where('setup', null));
});

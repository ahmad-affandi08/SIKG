<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Src\IdentityAccess\Domain\Role;
use Src\Pharmacy\Application\StockAlerts;
use Src\Pharmacy\Domain\StockAlertPolicy;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2025-06-16 08:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Klinik dengan satu perawat, tiga obat (A dipantau minimum 10, B minimum 0, C minimum 5), dan stok masuk.
 *
 * @return array<string, mixed>
 */
function siapStokPeringatan(): array
{
    $k = makeClinic();
    $perawat = makeUser(Role::Perawat, $k);
    $a = makeMedicine($k, ['name' => 'Amoksisilin', 'min_stock' => 10]);
    $b = makeMedicine($k, ['name' => 'Kapas', 'min_stock' => 0]);
    $c = makeMedicine($k, ['name' => 'Ibuprofen', 'min_stock' => 5]);
    $masuk = fn ($m, int $jumlah, ?string $batch = null, ?string $kedaluwarsa = null) => test()->actingAs($perawat)->post('/stock/movements', array_filter([
        'type' => 'in', 'medicine_id' => $m->id, 'quantity' => $jumlah, 'batch_no' => $batch, 'expiry_date' => $kedaluwarsa,
    ], fn ($v) => $v !== null))->assertSessionHasNoErrors();
    $masuk($a, 3, 'A-1', '2025-07-10');
    $masuk($b, 1);
    $masuk($c, 50, 'C-1', '2025-06-10');
    $masuk($c, 20, 'C-2', '2025-12-31');
    tenant()->set($k->id);

    return compact('k', 'perawat', 'a', 'b', 'c');
}

it('OBT-06 aturan: minimum 0 tidak dipantau, di bawah minimum diperingatkan, jendela 30 hari', function () {
    expect(StockAlertPolicy::isLow(0, 0))->toBeFalse()->and(StockAlertPolicy::isLow(9, 10))->toBeTrue()->and(StockAlertPolicy::isLow(10, 10))->toBeFalse()
        ->and(StockAlertPolicy::expiryLimit(new DateTimeImmutable('2025-06-16')))->toBe('2025-07-16')
        ->and(StockAlertPolicy::daysLeft(new DateTimeImmutable('2025-06-16'), '2025-06-30'))->toBe(14)
        ->and(StockAlertPolicy::daysLeft(new DateTimeImmutable('2025-06-16'), '2025-06-10'))->toBe(-6);
});

it('OBT-06 halaman stok menampilkan obat di bawah minimum dan batch kedaluwarsa dalam 30 hari atau sudah lewat', function () {
    $s = siapStokPeringatan();

    $this->actingAs($s['perawat'])->get('/stock')->assertOk()->assertInertia(function ($p) {
        $p->component('Stock/Index')->where('alerts.window_days', 30)
            ->has('alerts.low', 1)->where('alerts.low.0.name', 'Amoksisilin')->where('alerts.low.0.quantity', 3)->where('alerts.low.0.min_stock', 10)
            ->has('alerts.expiring', 2)
            ->where('alerts.expiring.0.name', 'Ibuprofen')->where('alerts.expiring.0.batch_no', 'C-1')->where('alerts.expiring.0.days_left', -6)
            ->where('alerts.expiring.1.name', 'Amoksisilin')->where('alerts.expiring.1.days_left', 24);

        return $p;
    });
});

it('OBT-06 batch tanpa stok tersisa tidak diperingatkan dan obat nonaktif tidak dihitung di bawah minimum', function () {
    $s = siapStokPeringatan();
    DB::table('medicines')->where('id', $s['a']->id)->update(['is_active' => false]);
    DB::table('stock_balances')->where('medicine_id', $s['c']->id)->update(['quantity' => 0]);
    DB::table('stock_batches')->where('medicine_id', $s['c']->id)->update(['remaining_quantity' => 0]);

    $alerts = app(StockAlerts::class)->overview();

    expect(array_column($alerts['low'], 'name'))->toBe(['Ibuprofen'])->and($alerts['expiring'])->toHaveCount(1)->and($alerts['expiring'][0]['name'])->toBe('Amoksisilin');
});

it('OBT-06 peringatan harian terbit sekali per hari, tanpa nama pasien, untuk pemegang stock.manage', function () {
    $s = siapStokPeringatan();
    $kasir = makeUser(Role::Kasir, $s['k']);
    tenant()->set($s['k']->id);

    $pertama = app(StockAlerts::class)->raiseDaily();
    $ulang = app(StockAlerts::class)->raiseDaily();

    expect($pertama)->toBe(['low' => true, 'expiring' => true])->and($ulang)->toBe(['low' => false, 'expiring' => false])
        ->and(DB::table('notifications')->count())->toBe(2);
    $low = DB::table('notifications')->where('kind', 'stock.low')->first();
    expect($low->dedupe_key)->toBe('stock.low:2025-06-16')->and($low->permission)->toBe('stock.manage')->and($low->link)->toBe('/stock')
        ->and($low->body)->toContain('Amoksisilin (3 dari minimum 10');
    $exp = DB::table('notifications')->where('kind', 'stock.expiring')->first();
    expect($exp->body)->toContain('Ibuprofen batch C-1 (sudah kedaluwarsa)')->and($exp->body)->toContain('Amoksisilin batch A-1 (24 hari lagi)');

    $this->actingAs($s['perawat'])->getJson('/internal-api/notifications')->assertJsonPath('data.unread', 2);
    $this->actingAs($kasir)->getJson('/internal-api/notifications')->assertJsonPath('data.unread', 0);

    Carbon::setTestNow(Carbon::parse('2025-06-17 08:00:00', 'Asia/Jakarta'));
    tenant()->set($s['k']->id);
    expect(app(StockAlerts::class)->raiseDaily())->toBe(['low' => true, 'expiring' => true])->and(DB::table('notifications')->count())->toBe(4);
});

it('OBT-06 pemberitahuan tidak terbit sebelum jam 07.00 lokal dan tidak ada bila semua stok aman', function () {
    $s = siapStokPeringatan();

    Carbon::setTestNow(Carbon::parse('2025-06-16 06:59:00', 'Asia/Jakarta'));
    expect(app(StockAlerts::class)->raiseDaily())->toBe(['low' => false, 'expiring' => false])->and(DB::table('notifications')->count())->toBe(0);

    DB::table('stock_balances')->where('medicine_id', $s['a']->id)->update(['quantity' => 50]);
    DB::table('stock_batches')->whereNotNull('expiry_date')->update(['expiry_date' => '2026-12-31']);
    Carbon::setTestNow(Carbon::parse('2025-06-16 08:00:00', 'Asia/Jakarta'));
    expect(app(StockAlerts::class)->raiseDaily())->toBe(['low' => false, 'expiring' => false]);
});

it('OBT-06 perintah terjadwal menerbitkan peringatan tiap klinik dan meneruskan ke tujuan klinik', function () {
    $s = siapStokPeringatan();
    DB::table('clinics')->where('id', $s['k']->id)->update(['settings' => json_encode(['notify_whatsapp' => ['628123450001']])]);
    rekamKirim();

    $this->artisan('sikg:stock:alerts')->assertSuccessful();
    $this->artisan('sikg:stock:alerts')->assertSuccessful();

    expect(DB::table('notifications')->count())->toBe(2)->and(KirimPerekam::$whatsapp)->toHaveCount(2)
        ->and(KirimPerekam::$whatsapp[0]['title'])->toBe('Stok obat menipis');
});

it('OBT-06 klinik lain tidak melihat peringatan stok klinik ini', function () {
    $s = siapStokPeringatan();
    $lain = makeClinic('Klinik Lain');
    $perawatLain = makeUser(Role::Perawat, $lain);
    tenant()->set($s['k']->id);
    app(StockAlerts::class)->raiseDaily();

    $this->actingAs($perawatLain)->getJson('/internal-api/notifications')->assertJsonPath('data.unread', 0)->assertJsonCount(0, 'data.items');
});

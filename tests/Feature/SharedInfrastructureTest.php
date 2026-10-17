<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\SequenceGenerator;
use Src\Shared\Application\Transaction;
use Src\Shared\Domain\DomainEvent;
use Src\Shared\Domain\DomainException;

final readonly class ContohKejadian implements DomainEvent
{
    public function __construct(public string $kunci) {}

    public function occurredAt(): DateTimeImmutable
    {
        return new DateTimeImmutable('2030-01-01 00:00:00');
    }

    public static function eventName(): string
    {
        return 'contoh.terjadi';
    }
}

final class ContohStokKurang extends DomainException
{
    public function __construct()
    {
        parent::__construct('stock.insufficient', 'Stok tidak cukup.');
    }

    public function httpStatus(): int
    {
        return 409;
    }
}

it('ADR-0004 nomor berurut naik satu per satu dan terpisah per lingkup', function () {
    $sequence = app(SequenceGenerator::class);

    expect($sequence->next('invoice_no', 'klinik-a'))->toBe(1)
        ->and($sequence->next('invoice_no', 'klinik-a'))->toBe(2)
        ->and($sequence->next('invoice_no', 'klinik-b'))->toBe(1)
        ->and($sequence->next('medical_record_no'))->toBe(1)
        ->and($sequence->next('invoice_no', 'klinik-a'))->toBe(3);
});

it('ADR-0004 nomor tidak bolong bila transaksi pemakainya dibatalkan', function () {
    $sequence = app(SequenceGenerator::class);
    $tx = app(Transaction::class);

    $sequence->next('invoice_no', 'k');
    try {
        $tx->run(function () use ($sequence): void {
            $sequence->next('invoice_no', 'k');
            throw new RuntimeException('gagal');
        });
    } catch (RuntimeException) {
    }

    expect($sequence->next('invoice_no', 'k'))->toBe(2);
});

it('M2 event domain dikirim setelah commit', function () {
    $diterima = [];
    Event::listen(ContohKejadian::class, function (ContohKejadian $e) use (&$diterima): void {
        $diterima[] = $e->kunci;
    });
    $dispatcher = app(EventDispatcher::class);

    app(Transaction::class)->run(function () use ($dispatcher, &$diterima): void {
        $dispatcher->dispatch(new ContohKejadian('a'));
        expect($diterima)->toBe([]);
    });

    expect($diterima)->toBe(['a']);
});

it('M2 event domain dibuang bila transaksi dibatalkan', function () {
    $diterima = [];
    Event::listen(ContohKejadian::class, function (ContohKejadian $e) use (&$diterima): void {
        $diterima[] = $e->kunci;
    });
    $dispatcher = app(EventDispatcher::class);

    try {
        app(Transaction::class)->run(function () use ($dispatcher): void {
            $dispatcher->dispatch(new ContohKejadian('batal'));
            throw new RuntimeException('gagal');
        });
    } catch (RuntimeException) {
    }

    expect($diterima)->toBe([]);
});

it('M2 transaksi mengembalikan hasil callback dan membatalkan semua perubahan saat gagal', function () {
    $tx = app(Transaction::class);

    expect($tx->run(fn () => 42))->toBe(42);

    try {
        $tx->run(function (): void {
            DB::table('system_heartbeats')->insert(['name' => 'uji', 'beat_at' => now()]);
            throw new RuntimeException('gagal');
        });
    } catch (RuntimeException) {
    }

    expect(DB::table('system_heartbeats')->where('name', 'uji')->exists())->toBeFalse();
});

it('JDW-02 galat domain dipetakan ke JSON dengan kode mesin yang stabil dan status 409', function () {
    Route::middleware('web')->get('/uji/stok', fn () => throw new ContohStokKurang);

    $this->getJson('/uji/stok')
        ->assertStatus(409)
        ->assertExactJson(['message' => 'Stok tidak cukup.', 'code' => 'stock.insufficient']);
});

it('JDW-02 galat domain pada halaman web kembali dengan pesan galat', function () {
    Route::middleware('web')->get('/uji/stok-web', fn () => throw new ContohStokKurang);

    $this->from('/dashboard')->get('/uji/stok-web')
        ->assertRedirect('/dashboard')
        ->assertSessionHasErrors(['domain' => 'Stok tidak cukup.']);
});

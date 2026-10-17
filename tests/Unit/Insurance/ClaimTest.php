<?php

declare(strict_types=1);

use Src\Insurance\Domain\Claim;
use Src\Insurance\Domain\ClaimStatus;
use Src\Insurance\Domain\InvalidClaim;
use Src\Shared\Domain\Money;

function klaimDraft(int $jumlah = 500000): Claim
{
    return Claim::draft('01HX0000000000000000000001', 'KLM-2025-00001', 'INV1', 'PAS1', 'INS1', null, Money::rupiah($jumlah), '  catatan  ');
}

function waktuKlaim(string $t = '2025-06-16 08:00:00'): DateTimeImmutable
{
    return new DateTimeImmutable($t, new DateTimeZone('UTC'));
}

it('P2-02 klaim baru berstatus draft dengan jumlah lebih dari nol dan belum menjadi piutang', function () {
    $klaim = klaimDraft();

    expect($klaim->status())->toBe(ClaimStatus::Draft)->and($klaim->claimed()->amount)->toBe(500000)->and($klaim->notes())->toBe('catatan')
        ->and($klaim->outstanding()->amount)->toBe(0);
    expect(fn () => klaimDraft(0))->toThrow(InvalidClaim::class, 'lebih dari nol');
});

it('P2-02 diajukan menjadi piutang sebesar jumlah klaim dan disetujui menjadi piutang sebesar jumlah disetujui', function () {
    $klaim = klaimDraft();
    $klaim->submit(waktuKlaim());
    expect($klaim->status())->toBe(ClaimStatus::Submitted)->and($klaim->outstanding()->amount)->toBe(500000);

    $klaim->approve(Money::rupiah(400000), 'Disetujui sebagian', waktuKlaim());
    expect($klaim->status())->toBe(ClaimStatus::Approved)->and($klaim->approved()?->amount)->toBe(400000)->and($klaim->outstanding()->amount)->toBe(400000)
        ->and($klaim->basis()->amount)->toBe(400000)->and($klaim->decisionNote())->toBe('Disetujui sebagian');
});

it('P2-02 jumlah disetujui tidak boleh nol atau melebihi jumlah diklaim', function () {
    $klaim = klaimDraft();
    $klaim->submit(waktuKlaim());

    expect(fn () => $klaim->approve(Money::rupiah(500001), null, waktuKlaim()))->toThrow(InvalidClaim::class, 'tidak melebihi')
        ->and(fn () => $klaim->approve(Money::zero(), null, waktuKlaim()))->toThrow(InvalidClaim::class);
});

it('P2-02 pembayaran bertahap melunasi klaim hanya saat seluruh jumlah disetujui diterima', function () {
    $klaim = klaimDraft();
    $klaim->submit(waktuKlaim());
    $klaim->approve(Money::rupiah(300000), null, waktuKlaim());

    $klaim->receive(Money::rupiah(100000), waktuKlaim('2025-06-17 08:00:00'));
    expect($klaim->status())->toBe(ClaimStatus::Approved)->and($klaim->outstanding()->amount)->toBe(200000)->and($klaim->paidAt())->toBeNull();

    $klaim->receive(Money::rupiah(200000), waktuKlaim('2025-06-18 08:00:00'));
    expect($klaim->status())->toBe(ClaimStatus::Paid)->and($klaim->outstanding()->amount)->toBe(0)->and($klaim->paidAt()?->format('Y-m-d'))->toBe('2025-06-18');
});

it('P2-02 pembayaran melebihi sisa, nol, atau sebelum disetujui ditolak', function () {
    $klaim = klaimDraft();
    expect(fn () => $klaim->receive(Money::rupiah(1), waktuKlaim()))->toThrow(InvalidClaim::class, 'sudah disetujui');

    $klaim->submit(waktuKlaim());
    $klaim->approve(Money::rupiah(100000), null, waktuKlaim());
    expect(fn () => $klaim->receive(Money::rupiah(100001), waktuKlaim()))->toThrow(InvalidClaim::class, 'melebihi')
        ->and(fn () => $klaim->receive(Money::zero(), waktuKlaim()))->toThrow(InvalidClaim::class);
});

it('P2-02 penolakan butuh alasan dan menghapus piutang', function () {
    $klaim = klaimDraft();
    $klaim->submit(waktuKlaim());

    expect(fn () => $klaim->reject('  ', waktuKlaim()))->toThrow(InvalidClaim::class, 'Alasan');
    $klaim->reject('Di luar manfaat polis', waktuKlaim());
    expect($klaim->status())->toBe(ClaimStatus::Rejected)->and($klaim->outstanding()->amount)->toBe(0)->and($klaim->decisionNote())->toBe('Di luar manfaat polis')
        ->and($klaim->status()->counts())->toBeFalse();
});

it('P2-02 hanya draft yang dapat diajukan atau dibatalkan dan hanya yang diajukan yang dapat diputuskan', function () {
    $klaim = klaimDraft();
    expect(fn () => $klaim->approve(Money::rupiah(1), null, waktuKlaim()))->toThrow(InvalidClaim::class, 'sudah diajukan');

    $klaim->cancel();
    expect($klaim->status())->toBe(ClaimStatus::Cancelled)->and($klaim->status()->counts())->toBeFalse()
        ->and(fn () => $klaim->submit(waktuKlaim()))->toThrow(InvalidClaim::class, 'draft');

    $lain = klaimDraft();
    $lain->submit(waktuKlaim());
    expect(fn () => $lain->cancel())->toThrow(InvalidClaim::class)->and(fn () => $lain->submit(waktuKlaim()))->toThrow(InvalidClaim::class);
});

it('P2-02 versi bertambah pada setiap perubahan status', function () {
    $klaim = klaimDraft();
    $klaim->submit(waktuKlaim());
    $klaim->approve(Money::rupiah(10), null, waktuKlaim());

    expect($klaim->version())->toBe(3);
});

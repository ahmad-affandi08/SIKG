<?php

declare(strict_types=1);

use Src\Billing\Domain\Discount;
use Src\Billing\Domain\DiscountKind;
use Src\Billing\Domain\InvalidBillingData;
use Src\Billing\Domain\Invoice;
use Src\Billing\Domain\InvoiceItem;
use Src\Billing\Domain\InvoiceStatus;
use Src\Billing\Domain\Payment;
use Src\Billing\Domain\PaymentType;
use Src\Shared\Domain\IdGenerator;
use Src\Shared\Domain\Money;

function idGen(): IdGenerator
{
    return new class implements IdGenerator
    {
        private int $n = 0;

        public function generate(): string
        {
            return str_pad('X'.(++$this->n), 26, '0');
        }
    };
}

function item(string $id, int $qty, int $price, string $type = InvoiceItem::SOURCE_MANUAL, ?string $source = null): InvoiceItem
{
    return InvoiceItem::create($id, $type, $source, 'Item '.$id, $qty, Money::rupiah($price));
}

function bayar(string $key, int $amount, PaymentType $type = PaymentType::Pay, string $method = 'M1'): Payment
{
    return new Payment('P'.$key, $method, $type, Money::rupiah($amount), new DateTimeImmutable('2025-06-16 03:00:00'), 'U1', null, $key, 'KW-'.$key, null);
}

function tagihan(int ...$totals): Invoice
{
    $invoice = Invoice::draft('I1', 'PAS', 'ENC');
    foreach ($totals as $i => $total) {
        $invoice->addClinicalItem(item('T'.$i, 1, $total, InvoiceItem::SOURCE_PROCEDURE, 'S'.$i));
    }

    return $invoice;
}

it('KSR-01 total diturunkan dari item dan diskon, bukan diisi dari luar (M7)', function () {
    $invoice = tagihan(100000, 50000);
    expect($invoice->subtotal()->amount)->toBe(150000)->and($invoice->total()->amount)->toBe(150000)
        ->and($invoice->status())->toBe(InvoiceStatus::Draft);

    $invoice->applyItemDiscount('T0', new Discount(DiscountKind::Percent, 1000, 'Pasien lama'));
    expect($invoice->item('T0')?->discountAmount()->amount)->toBe(10000)->and($invoice->total()->amount)->toBe(140000);

    $invoice->applyInvoiceDiscount(new Discount(DiscountKind::Amount, 5000, 'Promo'));
    expect($invoice->discountAmount()->amount)->toBe(5000)->and($invoice->total()->amount)->toBe(135000);
});

it('KSR-02 diskon wajib beralasan, persen 0,01 sampai 100, dan tidak melebihi dasarnya', function () {
    expect(fn () => new Discount(DiscountKind::Percent, 500, '  '))->toThrow(InvalidBillingData::class)
        ->and(fn () => new Discount(DiscountKind::Percent, 0, 'x'))->toThrow(InvalidBillingData::class)
        ->and(fn () => new Discount(DiscountKind::Percent, 10001, 'x'))->toThrow(InvalidBillingData::class)
        ->and(fn () => new Discount(DiscountKind::Amount, 0, 'x'))->toThrow(InvalidBillingData::class);

    $besar = new Discount(DiscountKind::Amount, 999999, 'Diskon besar');
    expect($besar->amountOn(Money::rupiah(20000))->amount)->toBe(20000);

    $dua = new Discount(DiscountKind::Percent, 1250, 'Uji pembulatan');
    expect($dua->amountOn(Money::rupiah(10001))->amount)->toBe(1250) // 1250,125 dibulatkan satu kali di Money
        ->and($dua->basisPointsOn(Money::rupiah(10001)))->toBeGreaterThanOrEqual(1249);
});

it('KSR-01 baris tindakan dan obat dari sumber yang sama tidak ganda dan hanya dilepas pada draft', function () {
    $invoice = tagihan(100000);
    expect(fn () => $invoice->addClinicalItem(item('DUP', 1, 100000, InvoiceItem::SOURCE_PROCEDURE, 'S0')))->toThrow(InvalidBillingData::class);

    expect($invoice->removeSourceItem(InvoiceItem::SOURCE_PROCEDURE, 'S0'))->toBeTrue()
        ->and($invoice->items())->toBe([])->and($invoice->removedItemIds())->toBe(['T0']);

    $invoice->addClinicalItem(item('T9', 1, 40000, InvoiceItem::SOURCE_PROCEDURE, 'S9'));
    $invoice->issue('INV-1', new DateTimeImmutable('2025-06-16'));
    expect($invoice->removeSourceItem(InvoiceItem::SOURCE_PROCEDURE, 'S9'))->toBeFalse()
        ->and(fn () => $invoice->addClinicalItem(item('T10', 1, 1000, InvoiceItem::SOURCE_PROCEDURE, 'S10')))->toThrow(InvalidBillingData::class);
});

it('KSR-01 kasir hanya mengubah item manual dan hanya sebelum ada pembayaran', function () {
    $invoice = tagihan(100000);
    $invoice->issue('INV-1', new DateTimeImmutable('2025-06-16'));
    $invoice->addManualItem(item('M1', 1, 20000));
    $invoice->changeManualQuantity('M1', 3);
    expect($invoice->total()->amount)->toBe(160000);

    expect(fn () => $invoice->changeManualQuantity('T0', 2))->toThrow(InvalidBillingData::class)
        ->and(fn () => $invoice->removeManualItem('T0'))->toThrow(InvalidBillingData::class);

    $invoice->removeManualItem('M1');
    expect($invoice->total()->amount)->toBe(100000);

    $invoice->recordPayment(bayar('k1', 10000));
    expect($invoice->isEditable())->toBeFalse()
        ->and(fn () => $invoice->addManualItem(item('M2', 1, 1000)))->toThrow(InvalidBillingData::class)
        ->and(fn () => $invoice->applyInvoiceDiscount(new Discount(DiscountKind::Amount, 1, 'x')))->toThrow(InvalidBillingData::class);
});

it('KSR-03 status diturunkan: belum dibayar, sebagian, lunas; pembayaran campuran dijumlahkan', function () {
    $invoice = tagihan(100000);
    $invoice->issue('INV-1', new DateTimeImmutable('2025-06-16'));
    expect($invoice->status())->toBe(InvoiceStatus::Unpaid);

    $invoice->recordPayment(bayar('a', 30000, PaymentType::Pay, 'TUNAI'));
    expect($invoice->status())->toBe(InvoiceStatus::Partial)->and($invoice->balance()->amount)->toBe(70000);

    $invoice->recordPayment(bayar('b', 70000, PaymentType::Pay, 'QRIS'));
    expect($invoice->status())->toBe(InvoiceStatus::Paid)->and($invoice->paid()->amount)->toBe(100000)->and($invoice->balance()->amount)->toBe(0)
        ->and($invoice->netPaidByMethod())->toBe(['TUNAI' => 30000, 'QRIS' => 70000]);
});

it('KSR-03 pembayaran melebihi sisa atau pada tagihan lunas ditolak dengan kode stabil', function () {
    $invoice = tagihan(100000);
    $invoice->issue('INV-1', new DateTimeImmutable('2025-06-16'));

    try {
        $invoice->recordPayment(bayar('a', 100001));
        $kode = null;
    } catch (InvalidBillingData $e) {
        $kode = [$e->businessCode(), $e->httpStatus()];
    }
    expect($kode)->toBe(['invoice.overpayment', 409]);

    $invoice->recordPayment(bayar('b', 100000));
    try {
        $invoice->recordPayment(bayar('c', 1));
        $kode = null;
    } catch (InvalidBillingData $e) {
        $kode = $e->businessCode();
    }
    expect($kode)->toBe('invoice.already_paid');
});

it('KSR-04 uang muka pada draft diizinkan, pembayaran biasa menunggu terbit, dan kelebihan DP menahan penerbitan', function () {
    $invoice = tagihan(100000);
    expect(fn () => $invoice->recordPayment(bayar('p', 10000)))->toThrow(InvalidBillingData::class);

    $invoice->recordPayment(bayar('dp', 40000, PaymentType::Dp));
    expect($invoice->paid()->amount)->toBe(40000)->and($invoice->status())->toBe(InvoiceStatus::Draft);

    $invoice->issue('INV-1', new DateTimeImmutable('2025-06-16'));
    expect($invoice->status())->toBe(InvoiceStatus::Partial)->and($invoice->balance()->amount)->toBe(60000);

    $lebih = tagihan(30000);
    $lebih->recordPayment(bayar('dp2', 50000, PaymentType::Dp));
    expect(fn () => $lebih->issue('INV-2', new DateTimeImmutable('2025-06-16')))->toThrow(InvalidBillingData::class);
});

it('M7 Idempotency-Key: kunci sama dengan isi sama tidak menambah, isi berbeda ditolak', function () {
    $invoice = tagihan(100000);
    $invoice->issue('INV-1', new DateTimeImmutable('2025-06-16'));

    expect($invoice->recordPayment(bayar('same', 25000)))->toBeTrue()
        ->and($invoice->recordPayment(bayar('same', 25000)))->toBeFalse()
        ->and($invoice->paid()->amount)->toBe(25000)->and($invoice->newPayments())->toHaveCount(1);

    try {
        $invoice->recordPayment(bayar('same', 30000));
        $kode = null;
    } catch (InvalidBillingData $e) {
        $kode = $e->businessCode();
    }
    expect($kode)->toBe('payment.idempotency_conflict');
});

it('KSR-07 refund mengurangi jumlah dibayar tanpa menghapus pembayaran, dengan alasan dan batas per metode', function () {
    $invoice = tagihan(100000);
    $invoice->issue('INV-1', new DateTimeImmutable('2025-06-16'));
    $invoice->recordPayment(bayar('a', 100000, PaymentType::Pay, 'TUNAI'));

    $refund = fn (string $key, int $amount, ?string $reason, string $method = 'TUNAI') => new Payment('R'.$key, $method, PaymentType::Refund, Money::rupiah($amount), new DateTimeImmutable('2025-06-16 04:00:00'), 'U1', null, $key, null, $reason);

    expect(fn () => $invoice->refund($refund('r0', 1000, ' ')))->toThrow(InvalidBillingData::class)
        ->and(fn () => $invoice->refund($refund('r1', 100001, 'Salah')))->toThrow(InvalidBillingData::class)
        ->and(fn () => $invoice->refund($refund('r2', 1000, 'Salah metode', 'QRIS')))->toThrow(InvalidBillingData::class);

    $invoice->refund($refund('r3', 40000, 'Tindakan dibatalkan'));
    expect($invoice->paid()->amount)->toBe(60000)->and($invoice->status())->toBe(InvoiceStatus::Partial)
        ->and($invoice->payments())->toHaveCount(2)->and($invoice->netPaidByMethod())->toBe(['TUNAI' => 60000]);
});

it('KSR-07 void mengembalikan uang lewat refund per metode, status Dibatalkan, dan tidak menghapus apa pun', function () {
    $invoice = tagihan(100000);
    $invoice->issue('INV-1', new DateTimeImmutable('2025-06-16'));
    $invoice->recordPayment(bayar('a', 30000, PaymentType::Pay, 'TUNAI'));
    $invoice->recordPayment(bayar('b', 50000, PaymentType::Pay, 'QRIS'));

    expect(fn () => $invoice->void('U2', ' ', new DateTimeImmutable('2025-06-16 05:00:00'), idGen()))->toThrow(InvalidBillingData::class);

    $refunds = $invoice->void('U2', 'Salah pasien', new DateTimeImmutable('2025-06-16 05:00:00'), idGen());
    expect($refunds)->toHaveCount(2)->and($invoice->status())->toBe(InvoiceStatus::Void)->and($invoice->paid()->amount)->toBe(0)
        ->and($invoice->payments())->toHaveCount(4)->and($invoice->voidReason())->toBe('Salah pasien')->and($invoice->voidedBy())->toBe('U2')
        ->and($invoice->items())->toHaveCount(1);

    expect(fn () => $invoice->void('U2', 'Lagi', new DateTimeImmutable('2025-06-16 06:00:00'), idGen()))->toThrow(InvalidBillingData::class)
        ->and(fn () => $invoice->recordPayment(bayar('z', 1)))->toThrow(InvalidBillingData::class);
});

it('KSR-01 tagihan tanpa item tidak dapat diterbitkan dan nomor hanya dipakai satu kali', function () {
    $kosong = Invoice::draft('I2', 'PAS', 'ENC2');
    expect(fn () => $kosong->issue('INV-X', new DateTimeImmutable('2025-06-16')))->toThrow(InvalidBillingData::class);

    $invoice = tagihan(1000);
    $invoice->issue('INV-1', new DateTimeImmutable('2025-06-16'));
    expect(fn () => $invoice->issue('INV-2', new DateTimeImmutable('2025-06-16')))->toThrow(InvalidBillingData::class)
        ->and($invoice->number())->toBe('INV-1');
});

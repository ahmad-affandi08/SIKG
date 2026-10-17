<?php

// TEMPLAT: salin ke tests/Feature/Billing/IdempotentPaymentTest.php

declare(strict_types=1);

it('KSR-03 header Idempotency-Key yang sama tidak membuat pembayaran ganda', function () {
    $kasir = cashierUser();
    $invoice = createIssuedInvoice(total: 500_000);
    $payload = ['payment_method_id' => cashMethodId(), 'amount' => 500_000];

    $headers = ['Idempotency-Key' => 'uji-key-1'];
    $this->actingAs($kasir)->postJson("/invoices/{$invoice->id}/payments", $payload, $headers)->assertSuccessful();
    $this->actingAs($kasir)->postJson("/invoices/{$invoice->id}/payments", $payload, $headers)->assertSuccessful();

    expect(paymentCount($invoice))->toBe(1)
        ->and(invoiceStatus($invoice))->toBe('paid');
});

it('KSR-04 pembayaran sebagian menyisakan piutang dan status partially_paid', function () {
    $kasir = cashierUser();
    $invoice = createIssuedInvoice(total: 500_000);

    $this->actingAs($kasir)->postJson("/invoices/{$invoice->id}/payments", ['payment_method_id' => cashMethodId(), 'amount' => 200_000], ['Idempotency-Key' => 'k2'])->assertSuccessful();

    expect(invoiceStatus($invoice))->toBe('partially_paid')
        ->and(outstanding($invoice))->toBe(300_000);
});

it('KSR-03 pembayaran melebihi sisa ditolak dengan kode invoice.overpayment', function () {
    $kasir = cashierUser();
    $invoice = createIssuedInvoice(total: 100_000);

    $this->actingAs($kasir)->postJson("/invoices/{$invoice->id}/payments", ['payment_method_id' => cashMethodId(), 'amount' => 100_001], ['Idempotency-Key' => 'k3'])
        ->assertStatus(422)->assertJsonPath('code', 'invoice.overpayment');
});

it('KSR-07 void membuat transaksi refund dan tidak menghapus pembayaran', function () {
    $admin = adminUser();
    $invoice = createPaidInvoice(total: 300_000);

    $this->actingAs($admin)->post("/invoices/{$invoice->id}/void", ['reason' => 'salah input'])->assertRedirect();

    expect(invoiceStatus($invoice))->toBe('void')
        ->and(paymentTypes($invoice))->toEqualCanonicalizing(['payment', 'refund']);
});

it('KSR-07 kasir tidak boleh void', function () {
    $this->actingAs(cashierUser())->post('/invoices/' . createPaidInvoice(total: 1000)->id . '/void', ['reason' => 'x'])->assertForbidden();
});

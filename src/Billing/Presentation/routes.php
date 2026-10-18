<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Billing\Presentation\Http\Controllers\CashClosingController;
use Src\Billing\Presentation\Http\Controllers\ExpenseController;
use Src\Billing\Presentation\Http\Controllers\InstallmentController;
use Src\Billing\Presentation\Http\Controllers\InvoiceController;
use Src\Billing\Presentation\Http\Controllers\InvoicePrintController;
use Src\Billing\Presentation\Http\Controllers\LoyaltyController;
use Src\Billing\Presentation\Http\Controllers\PaymentController;
use Src\Billing\Presentation\Http\Controllers\PromotionController;

// Semua route di balik 'auth' (dipasang oleh ServiceProvider).
// Setiap route wajib punya pemeriksaan izin: ->middleware('can:<permission>') atau authorize() di controller.

$id = '[0-9A-Z]{26}';

Route::middleware('can:invoice.view')->group(function () use ($id): void {
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show')->where('invoice', $id);
    Route::get('/invoices/{invoice}/print', InvoicePrintController::class)->name('invoices.print')->where('invoice', $id);
});

Route::middleware(['can:invoice.view', 'throttle:60,1'])->prefix('internal-api')->group(function () use ($id): void {
    Route::get('/invoices/{invoice}/summary', [PaymentController::class, 'summary'])->where('invoice', $id);
});

Route::middleware('can:invoice.edit')->group(function () use ($id): void {
    Route::post('/invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->where('invoice', $id);
    Route::get('/invoices/{invoice}/items/create', [InvoiceController::class, 'itemForm'])->name('invoices.items.create')->where('invoice', $id);
    Route::post('/invoices/{invoice}/items', [InvoiceController::class, 'storeItem'])->where('invoice', $id);
    Route::get('/invoices/{invoice}/items/{item}/edit', [InvoiceController::class, 'editItem'])->name('invoices.items.edit')->where(['invoice' => $id, 'item' => $id]);
    Route::put('/invoices/{invoice}/items/{item}', [InvoiceController::class, 'updateItem'])->where(['invoice' => $id, 'item' => $id]);
    Route::post('/invoices/{invoice}/items/{item}/remove', [InvoiceController::class, 'removeItem'])->where(['invoice' => $id, 'item' => $id]);
});

Route::middleware('can:invoice.discount')->group(function () use ($id): void {
    Route::get('/invoices/{invoice}/discount', [InvoiceController::class, 'discountForm'])->name('invoices.discount')->where('invoice', $id);
    Route::post('/invoices/{invoice}/discount', [InvoiceController::class, 'storeDiscount'])->where('invoice', $id);
    Route::get('/invoices/{invoice}/items/{item}/discount', [InvoiceController::class, 'discountForm'])->name('invoices.items.discount')->where(['invoice' => $id, 'item' => $id]);
    Route::post('/invoices/{invoice}/items/{item}/discount', [InvoiceController::class, 'storeDiscount'])->where(['invoice' => $id, 'item' => $id]);
});

Route::middleware('can:payment.create')->group(function () use ($id): void {
    Route::get('/invoices/{invoice}/payments/create', [PaymentController::class, 'createForm'])->name('invoices.payments.create')->where('invoice', $id);
    Route::post('/invoices/{invoice}/payments', [PaymentController::class, 'store'])->where('invoice', $id);
    Route::get('/invoices/{invoice}/payment-plan/create', [InstallmentController::class, 'create'])->name('invoices.payment-plan.create')->where('invoice', $id);
    Route::post('/invoices/{invoice}/payment-plan', [InstallmentController::class, 'store'])->where('invoice', $id);
});

Route::middleware('can:payment.refund')->group(function () use ($id): void {
    Route::get('/invoices/{invoice}/refunds/create', [PaymentController::class, 'refundForm'])->name('invoices.refunds.create')->where('invoice', $id);
    Route::post('/invoices/{invoice}/refunds', [PaymentController::class, 'refund'])->where('invoice', $id);
});

Route::middleware('can:invoice.void')->group(function () use ($id): void {
    Route::get('/invoices/{invoice}/void', [PaymentController::class, 'voidForm'])->name('invoices.void')->where('invoice', $id);
    Route::post('/invoices/{invoice}/void', [PaymentController::class, 'void'])->where('invoice', $id);
});

Route::middleware('can:cash.close')->group(function (): void {
    Route::get('/cash-closings', [CashClosingController::class, 'index'])->name('cash-closings.index');
    Route::get('/cash-closings/create', [CashClosingController::class, 'create'])->name('cash-closings.create');
    Route::post('/cash-closings', [CashClosingController::class, 'store']);
});

// P2-08: biaya operasional. Izin expense.view dan expense.manage didefinisikan di BillingServiceProvider.
Route::middleware('can:expense.view')->group(function (): void {
    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
});

Route::middleware('can:expense.manage')->group(function () use ($id): void {
    Route::get('/expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
    Route::post('/expenses', [ExpenseController::class, 'store']);
    Route::post('/expenses/{expense}/void', [ExpenseController::class, 'void'])->where('expense', $id);
});

// P3-06: voucher, poin, dan paket kunjungan.
Route::middleware('can:invoice.view')->group(function () use ($id): void {
    Route::get('/invoices/{invoice}/promotions', [PromotionController::class, 'show'])->name('invoices.promotions')->where('invoice', $id);
});

Route::middleware('can:invoice.edit')->group(function () use ($id): void {
    Route::post('/invoices/{invoice}/promotions/voucher', [PromotionController::class, 'voucher'])->where('invoice', $id)->middleware('throttle:30,1');
    Route::post('/invoices/{invoice}/promotions/points', [PromotionController::class, 'points'])->where('invoice', $id);
    Route::post('/invoices/{invoice}/promotions/remove', [PromotionController::class, 'remove'])->where('invoice', $id);
    Route::post('/invoices/{invoice}/promotions/package', [PromotionController::class, 'package'])->where('invoice', $id);
    Route::post('/invoices/{invoice}/promotions/package/remove', [PromotionController::class, 'removePackage'])->where('invoice', $id);
    Route::post('/patients/{patient}/packages', [LoyaltyController::class, 'sell'])->where('patient', $id);
});

Route::middleware('can:patient.view')->group(function () use ($id): void {
    Route::get('/patients/{patient}/loyalty', [LoyaltyController::class, 'patient'])->name('patients.loyalty')->where('patient', $id);
});

Route::middleware('can:setting.manage')->group(function (): void {
    Route::get('/settings/loyalty', [LoyaltyController::class, 'settings'])->name('settings.loyalty');
    Route::post('/settings/loyalty', [LoyaltyController::class, 'saveSettings']);
});

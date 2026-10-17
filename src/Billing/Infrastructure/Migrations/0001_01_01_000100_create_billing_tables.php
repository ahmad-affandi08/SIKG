<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// KSR-01 sampai KSR-04, KSR-06 sampai KSR-08: tagihan, item, pembayaran, dan tutup kas. Uang selalu BIGINT rupiah (M4).
// Pembayaran hanya bertambah (koreksi berupa transaksi pembalik); tagihan dan item tidak dihapus permanen (M5).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->string('number', 40)->nullable();
            $table->char('patient_id', 26);
            $table->char('encounter_id', 26);
            $table->string('lifecycle', 8); // draft, issued, void
            $table->string('status', 10); // draft, unpaid, partial, paid, void (turunan domain)
            $table->unsignedBigInteger('subtotal_amount')->default(0);
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->string('discount_type', 8)->nullable();
            $table->unsignedInteger('discount_value')->nullable();
            $table->string('discount_reason', 255)->nullable();
            $table->dateTime('issued_at')->nullable();
            $table->dateTime('voided_at')->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->char('voided_by', 26)->nullable();
            $table->char('created_by', 26)->nullable();
            $table->char('updated_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('patient_id')->references('id')->on('patients')->restrictOnDelete();
            $table->foreign('encounter_id')->references('id')->on('encounters')->restrictOnDelete();
            $table->unique(['clinic_id', 'number']);
            $table->unique('encounter_id');
            $table->index(['status', 'issued_at']);
            $table->index('patient_id');
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('invoice_id', 26);
            $table->string('source_type', 12); // procedure, medicine, manual
            $table->char('source_id', 26)->nullable();
            $table->string('description', 255);
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_amount'); // salinan tarif saat item dibuat (MST-08)
            $table->string('discount_type', 8)->nullable();
            $table->unsignedInteger('discount_value')->nullable();
            $table->string('discount_reason', 255)->nullable();
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('total_amount');
            $table->char('created_by', 26)->nullable();
            $table->char('updated_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('invoice_id')->references('id')->on('invoices')->restrictOnDelete();
            $table->unique(['source_type', 'source_id']);
            $table->index('invoice_id');
        });

        Schema::create('cash_closings', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->date('closing_date');
            $table->char('cashier_id', 26);
            $table->bigInteger('system_amount'); // bertanda: refund tunai dapat melebihi penerimaan
            $table->unsignedBigInteger('counted_amount');
            $table->bigInteger('difference_amount'); // fisik dikurangi sistem
            $table->string('note', 255)->nullable();
            $table->dateTime('closed_at');
            $table->char('created_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->index(['closing_date', 'cashier_id']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('invoice_id', 26);
            $table->char('payment_method_id', 26);
            $table->string('type', 8); // pay, dp, refund
            $table->unsignedBigInteger('amount');
            $table->dateTime('paid_at');
            $table->char('received_by', 26);
            $table->string('reference_no', 100)->nullable();
            $table->string('idempotency_key', 100);
            $table->string('receipt_no', 40)->nullable();
            $table->string('reason', 255)->nullable();
            $table->char('cash_closing_id', 26)->nullable(); // diisi sekali saat tutup kas; kolom lain tidak pernah berubah
            $table->dateTime('created_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('invoice_id')->references('id')->on('invoices')->restrictOnDelete();
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->restrictOnDelete();
            $table->foreign('cash_closing_id')->references('id')->on('cash_closings')->restrictOnDelete();
            $table->unique(['invoice_id', 'idempotency_key']);
            $table->unique(['clinic_id', 'receipt_no']);
            $table->index(['paid_at', 'payment_method_id']);
            $table->index(['received_by', 'cash_closing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('cash_closings');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// KSR-09: tarif pajak disalin ke tagihan saat dibuat (bawaan 0). KSR-05: jadwal cicilan; status tiap cicilan diturunkan dari
// pembayaran, bukan disimpan.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedSmallInteger('tax_basis_points')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
        });

        Schema::create('installment_plans', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('invoice_id', 26);
            $table->unsignedSmallInteger('installment_count');
            $table->unsignedBigInteger('base_paid_amount'); // sudah dibayar saat jadwal dibuat; cicilan dihitung dari pembayaran setelahnya
            $table->char('created_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('invoice_id')->references('id')->on('invoices')->restrictOnDelete();
            $table->unique('invoice_id');
        });

        Schema::create('installments', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('plan_id', 26);
            $table->char('invoice_id', 26);
            $table->unsignedSmallInteger('sequence');
            $table->date('due_date');
            $table->unsignedBigInteger('amount');
            $table->dateTime('created_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('plan_id')->references('id')->on('installment_plans')->restrictOnDelete();
            $table->foreign('invoice_id')->references('id')->on('invoices')->restrictOnDelete();
            $table->unique(['plan_id', 'sequence']);
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installments');
        Schema::dropIfExists('installment_plans');
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['tax_basis_points', 'tax_amount']);
        });
    }
};

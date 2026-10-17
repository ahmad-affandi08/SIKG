<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// P2-02: penjamin asuransi dan perusahaan, polis per pasien (dengan plafon), tagihan ke penjamin (klaim) dan penerimaannya.
// Uang BIGINT rupiah (M4). Penerimaan klaim hanya bertambah; setiap penerimaan juga tercatat sebagai pembayaran pada tagihan.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurers', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->string('name', 150);
            $table->string('type', 12); // insurance, corporate
            $table->string('contact_name', 100)->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('contact_email', 150)->nullable();
            $table->string('notes', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->char('created_by', 26)->nullable();
            $table->char('updated_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->index(['clinic_id', 'name']);
        });

        Schema::create('patient_policies', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('patient_id', 26);
            $table->char('insurer_id', 26);
            $table->string('policy_no', 60);
            $table->unsignedBigInteger('ceiling_amount')->nullable(); // plafon; kosong = tanpa batas
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->string('notes', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->char('created_by', 26)->nullable();
            $table->char('updated_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('patient_id')->references('id')->on('patients')->restrictOnDelete();
            $table->foreign('insurer_id')->references('id')->on('insurers')->restrictOnDelete();
            $table->index(['patient_id', 'is_active']);
        });

        Schema::create('insurance_claims', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->string('claim_no', 30);
            $table->char('invoice_id', 26);
            $table->char('patient_id', 26);
            $table->char('insurer_id', 26);
            $table->char('policy_id', 26)->nullable();
            $table->unsignedBigInteger('claimed_amount');
            $table->unsignedBigInteger('approved_amount')->nullable();
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->string('status', 12); // draft, submitted, approved, rejected, paid, cancelled
            $table->string('notes', 500)->nullable();
            $table->string('decision_note', 255)->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->char('created_by', 26)->nullable();
            $table->char('updated_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('invoice_id')->references('id')->on('invoices')->restrictOnDelete();
            $table->foreign('patient_id')->references('id')->on('patients')->restrictOnDelete();
            $table->foreign('insurer_id')->references('id')->on('insurers')->restrictOnDelete();
            $table->foreign('policy_id')->references('id')->on('patient_policies')->restrictOnDelete();
            $table->unique(['clinic_id', 'claim_no']);
            $table->index(['status', 'insurer_id']);
            $table->index('invoice_id');
        });

        Schema::create('insurance_claim_receipts', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('claim_id', 26);
            $table->unsignedBigInteger('amount');
            $table->char('method_id', 26);
            $table->string('reference_no', 100)->nullable();
            $table->string('idempotency_key', 80);
            $table->dateTime('received_at');
            $table->char('received_by', 26);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('claim_id')->references('id')->on('insurance_claims')->restrictOnDelete();
            $table->unique(['claim_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_claim_receipts');
        Schema::dropIfExists('insurance_claims');
        Schema::dropIfExists('patient_policies');
        Schema::dropIfExists('insurers');
    }
};

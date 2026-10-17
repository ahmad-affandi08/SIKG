<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// P2-04: paket perawatan ortodonti, jadwal cicilan, dan catatan kontrol bulanan. Uang BIGINT rupiah (M4). Catatan kontrol hanya
// bertambah (M6); cicilan ditagihkan ke tagihan kunjungan lewat Billing sehingga pembayarannya tercatat di sana (M7).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orthodontic_cases', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('patient_id', 26);
            $table->char('doctor_id', 26);
            $table->string('appliance', 16);
            $table->string('title', 150);
            $table->string('plan_notes', 1000)->nullable();
            $table->unsignedBigInteger('total_fee_amount');
            $table->date('start_date');
            $table->unsignedTinyInteger('expected_months');
            $table->string('status', 10); // active, completed, stopped
            $table->dateTime('ended_at')->nullable();
            $table->string('ended_note', 255)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->char('created_by', 26)->nullable();
            $table->char('updated_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('patient_id')->references('id')->on('patients')->restrictOnDelete();
            $table->foreign('doctor_id')->references('id')->on('doctors')->restrictOnDelete();
            $table->index(['status', 'patient_id']);
        });

        Schema::create('orthodontic_installments', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('case_id', 26);
            $table->unsignedSmallInteger('seq');
            $table->date('due_date');
            $table->unsignedBigInteger('amount');
            $table->string('status', 10); // scheduled, invoiced, cancelled
            $table->char('encounter_id', 26)->nullable();
            $table->char('invoice_id', 26)->nullable();
            $table->dateTime('invoiced_at')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('case_id')->references('id')->on('orthodontic_cases')->restrictOnDelete();
            $table->foreign('encounter_id')->references('id')->on('encounters')->restrictOnDelete();
            $table->foreign('invoice_id')->references('id')->on('invoices')->restrictOnDelete();
            $table->unique(['case_id', 'seq']);
        });

        Schema::create('orthodontic_controls', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('case_id', 26);
            $table->date('control_date');
            $table->char('encounter_id', 26)->nullable();
            $table->string('wire_or_tray', 100)->nullable();
            $table->string('adjustments', 1000)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->date('next_control_date')->nullable();
            $table->char('created_by', 26);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('case_id')->references('id')->on('orthodontic_cases')->restrictOnDelete();
            $table->foreign('encounter_id')->references('id')->on('encounters')->restrictOnDelete();
            $table->index(['case_id', 'control_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orthodontic_controls');
        Schema::dropIfExists('orthodontic_installments');
        Schema::dropIfExists('orthodontic_cases');
    }
};

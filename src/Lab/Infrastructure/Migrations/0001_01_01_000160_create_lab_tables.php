<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// P2-06: laboratorium gigi dan pesanan pekerjaan lab. Uang BIGINT rupiah (M4). Biaya lab (dibayar klinik) dan harga ke pasien
// dicatat terpisah; harga ke pasien ditagihkan lewat Billing sehingga pembayarannya tercatat di sana (M7).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laboratories', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->string('name', 150);
            $table->string('contact_name', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('address', 255)->nullable();
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

        Schema::create('lab_orders', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->string('order_no', 30);
            $table->char('patient_id', 26);
            $table->char('doctor_id', 26);
            $table->char('laboratory_id', 26);
            $table->string('work_type', 12);
            $table->string('tooth_notes', 100)->default('');
            $table->string('description', 500);
            $table->string('shade', 30)->nullable();
            $table->string('status', 10); // ordered, sent, received, cancelled
            $table->date('ordered_date');
            $table->date('due_date')->nullable();
            $table->date('sent_date')->nullable();
            $table->date('received_date')->nullable();
            $table->unsignedBigInteger('lab_cost_amount');
            $table->unsignedBigInteger('patient_charge_amount');
            $table->char('invoice_id', 26)->nullable();
            $table->string('cancel_note', 255)->nullable();
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
            $table->foreign('laboratory_id')->references('id')->on('laboratories')->restrictOnDelete();
            $table->foreign('invoice_id')->references('id')->on('invoices')->restrictOnDelete();
            $table->unique(['clinic_id', 'order_no']);
            $table->index(['status', 'due_date']);
            $table->index(['doctor_id', 'received_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_orders');
        Schema::dropIfExists('laboratories');
    }
};

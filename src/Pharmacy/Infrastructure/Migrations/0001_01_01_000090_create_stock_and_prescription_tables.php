<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// OBT-01, OBT-04, OBT-05: saldo dan mutasi stok, resep dan itemnya. Stok tidak pernah negatif (kecuali lewat penyesuaian
// bertanda); mutasi hanya bertambah (tidak diubah atau dihapus), koreksi berupa mutasi penyesuaian.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('medicine_id', 26);
            $table->unsignedInteger('quantity')->default(0);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('medicine_id')->references('id')->on('medicines')->restrictOnDelete();
            $table->unique('medicine_id');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('medicine_id', 26);
            $table->string('type', 12); // in, out, adjustment
            $table->integer('quantity'); // selisih bertanda: masuk positif, keluar negatif
            $table->unsignedInteger('balance_after');
            $table->string('reference_type', 30)->nullable(); // prescription_item, invoice_item, manual
            $table->char('reference_id', 26)->nullable();
            $table->string('reason', 255)->nullable();
            $table->string('batch_no', 50)->nullable();
            $table->date('expiry_date')->nullable();
            $table->char('created_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('medicine_id')->references('id')->on('medicines')->restrictOnDelete();
            $table->index(['medicine_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('prescriptions', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('encounter_id', 26);
            $table->char('patient_id', 26);
            $table->char('doctor_id', 26);
            $table->dateTime('issued_at');
            $table->string('status', 10); // issued, void
            $table->string('void_reason', 255)->nullable();
            $table->string('allergy_acknowledged', 500)->nullable();
            $table->char('created_by', 26)->nullable();
            $table->char('updated_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('encounter_id')->references('id')->on('encounters')->restrictOnDelete();
            $table->foreign('patient_id')->references('id')->on('patients')->restrictOnDelete();
            $table->foreign('doctor_id')->references('id')->on('doctors')->restrictOnDelete();
            $table->index('encounter_id');
            $table->index(['patient_id', 'issued_at']);
        });

        Schema::create('prescription_items', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('prescription_id', 26);
            $table->char('medicine_id', 26)->nullable();
            $table->string('free_text_name')->nullable();
            $table->string('medicine_name'); // salinan nama saat resep dibuat
            $table->string('dose', 100);
            $table->string('frequency', 100);
            $table->string('duration', 100);
            $table->string('instructions', 500)->nullable();
            $table->unsignedSmallInteger('quantity');
            $table->boolean('dispense_in_clinic')->default(false);
            $table->unsignedBigInteger('unit_price_amount')->default(0); // salinan harga jual saat dijual
            $table->dateTime('dispensed_at')->nullable();
            $table->char('created_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('prescription_id')->references('id')->on('prescriptions')->restrictOnDelete();
            $table->foreign('medicine_id')->references('id')->on('medicines')->restrictOnDelete();
            $table->index('prescription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_balances');
    }
};

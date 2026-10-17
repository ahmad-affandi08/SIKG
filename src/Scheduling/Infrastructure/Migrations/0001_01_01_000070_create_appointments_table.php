<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// JDW-01 sampai JDW-08: janji temu dan antrean harian. Waktu disimpan UTC; `queue_date` adalah tanggal lokal klinik.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('patient_id', 26);
            $table->char('doctor_id', 26);
            $table->char('chair_id', 26)->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 20);
            $table->string('source', 10); // booking, walkin
            $table->unsignedInteger('queue_no')->nullable();
            $table->date('queue_date')->nullable();
            $table->string('complaint', 500)->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->string('reschedule_reason', 255)->nullable();
            $table->dateTime('checked_in_at')->nullable();
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
            $table->foreign('chair_id')->references('id')->on('chairs')->restrictOnDelete();
            $table->index(['doctor_id', 'starts_at']);
            $table->index(['chair_id', 'starts_at']);
            $table->index(['status', 'starts_at']);
            $table->index(['patient_id', 'starts_at']);
            $table->unique(['clinic_id', 'queue_date', 'queue_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};

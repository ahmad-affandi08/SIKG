<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// P2-01: integrasi SATUSEHAT. Kredensial dan NIK dokter dienkripsi di aplikasi (M11, M13). Antrean pengiriman memuat status dan
// galat yang sudah dibersihkan, tanpa isi pesan FHIR.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('satusehat_settings', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->boolean('enabled')->default(false);
            $table->string('environment', 12)->default('staging');
            $table->string('organization_id', 64)->nullable();
            $table->string('location_id', 64)->nullable();
            $table->string('location_name', 150)->nullable();
            $table->string('client_id', 128)->nullable();
            $table->text('client_secret_encrypted')->nullable();
            $table->char('created_by', 26)->nullable();
            $table->char('updated_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->unique('clinic_id');
        });

        Schema::create('satusehat_practitioners', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('doctor_id', 26);
            $table->text('nik_encrypted')->nullable();
            $table->string('ihs_id', 64)->nullable();
            $table->char('created_by', 26)->nullable();
            $table->char('updated_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('doctor_id')->references('id')->on('doctors')->restrictOnDelete();
            $table->unique('doctor_id');
        });

        Schema::create('satusehat_patients', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('patient_id', 26);
            $table->string('ihs_id', 64);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('patient_id')->references('id')->on('patients')->restrictOnDelete();
            $table->unique('patient_id');
        });

        Schema::create('satusehat_submissions', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('encounter_id', 26);
            $table->char('patient_id', 26);
            $table->string('status', 10); // pending, sent, failed, blocked, skipped
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('next_attempt_at')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->string('encounter_ihs_id', 64)->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('encounter_id')->references('id')->on('encounters')->restrictOnDelete();
            $table->foreign('patient_id')->references('id')->on('patients')->restrictOnDelete();
            $table->unique('encounter_id');
            $table->index(['status', 'next_attempt_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satusehat_submissions');
        Schema::dropIfExists('satusehat_patients');
        Schema::dropIfExists('satusehat_practitioners');
        Schema::dropIfExists('satusehat_settings');
    }
};

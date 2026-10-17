<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PAS-01 sampai PAS-06: pasien, wali, riwayat medis, persetujuan data (docs/rules/database.md).
// NIK, nomor HP, dan kontak darurat disimpan terenkripsi; kolom *_hash adalah blind index HMAC untuk pencarian persis (M11).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->string('medical_record_no', 40);
            $table->string('name');
            $table->string('name_search');
            $table->text('nik_encrypted')->nullable();
            $table->char('nik_hash', 64)->nullable();
            $table->string('nik_missing_reason', 255)->nullable();
            $table->date('birth_date');
            $table->string('sex', 10);
            $table->text('address')->nullable();
            $table->text('phone_encrypted')->nullable();
            $table->char('phone_hash', 64)->nullable();
            $table->string('occupation', 100)->nullable();
            $table->text('emergency_contact')->nullable();
            $table->dateTime('data_consent_at')->nullable();
            $this->standard($table);

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->unique(['clinic_id', 'medical_record_no']);
            $table->index(['clinic_id', 'nik_hash']);
            $table->index(['clinic_id', 'phone_hash']);
            $table->index(['clinic_id', 'name_search']);
            $table->index(['clinic_id', 'birth_date']);
        });

        Schema::create('patient_guardians', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('patient_id', 26);
            $table->string('name');
            $table->string('relation', 50);
            $table->text('phone_encrypted')->nullable();
            $this->standard($table);

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('patient_id')->references('id')->on('patients')->restrictOnDelete();
            $table->unique('patient_id');
        });

        Schema::create('patient_medical_histories', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('patient_id', 26);
            $table->string('type', 30); // allergy, systemic_disease, regular_medication, pregnancy
            $table->string('name');
            $table->string('severity', 20)->nullable();
            $table->string('notes', 1000)->nullable();
            $this->standard($table);

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('patient_id')->references('id')->on('patients')->restrictOnDelete();
            $table->index(['patient_id', 'type']);
        });

        Schema::create('patient_consents', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('patient_id', 26);
            $table->string('text_version', 20);
            $table->dateTime('given_at');
            $table->char('recorded_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('patient_id')->references('id')->on('patients')->restrictOnDelete();
            $table->index(['patient_id', 'given_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_consents');
        Schema::dropIfExists('patient_medical_histories');
        Schema::dropIfExists('patient_guardians');
        Schema::dropIfExists('patients');
    }

    private function standard(Blueprint $table): void
    {
        $table->char('created_by', 26)->nullable();
        $table->char('updated_by', 26)->nullable();
        $table->dateTime('created_at')->nullable();
        $table->dateTime('updated_at')->nullable();
        $table->dateTime('deleted_at')->nullable();
    }
};

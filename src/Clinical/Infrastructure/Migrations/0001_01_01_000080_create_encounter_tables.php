<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// RM-01 sampai RM-07: kunjungan, catatan klinis (SOAP, anamnesis, addendum), diagnosa, dan tindakan.
// Catatan klinis hanya bertambah: koreksi membuat baris baru yang menunjuk baris lama lewat `supersedes_id` (M5, M6).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounters', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('patient_id', 26);
            $table->char('appointment_id', 26)->nullable();
            $table->char('doctor_id', 26);
            $table->char('chair_id', 26)->nullable();
            $table->dateTime('started_at');
            $table->dateTime('closed_at')->nullable();
            $table->char('closed_by', 26)->nullable();
            $table->string('status', 10); // open, closed
            $table->text('chief_complaint')->nullable();
            $table->text('complaint_history')->nullable();
            $table->json('vitals')->nullable();
            $table->dateTime('anamnesis_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $this->standard($table);

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('patient_id')->references('id')->on('patients')->restrictOnDelete();
            $table->foreign('appointment_id')->references('id')->on('appointments')->restrictOnDelete();
            $table->foreign('doctor_id')->references('id')->on('doctors')->restrictOnDelete();
            $table->foreign('chair_id')->references('id')->on('chairs')->restrictOnDelete();
            $table->unique('appointment_id');
            $table->index(['patient_id', 'started_at']);
            $table->index(['clinic_id', 'status']);
        });

        Schema::create('clinical_notes', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('encounter_id', 26);
            $table->string('type', 10); // S, O, A, P, addendum
            $table->string('section', 20)->nullable(); // O: extra_oral, intra_oral
            $table->text('body');
            $table->char('author_id', 26);
            $table->dateTime('authored_at');
            $table->char('supersedes_id', 26)->nullable();
            $table->string('reason', 255)->nullable();
            $table->dateTime('created_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('encounter_id')->references('id')->on('encounters')->restrictOnDelete();
            $table->foreign('author_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('supersedes_id')->references('id')->on('clinical_notes')->restrictOnDelete();
            $table->index(['encounter_id', 'type']);
            $table->unique('supersedes_id');
        });

        Schema::create('encounter_diagnoses', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('encounter_id', 26);
            $table->char('diagnosis_id', 26);
            $table->string('icd10_code', 10);
            $table->string('diagnosis_name');
            $table->unsignedTinyInteger('tooth_fdi')->nullable();
            $table->string('notes', 500)->nullable();
            $this->standard($table);

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('encounter_id')->references('id')->on('encounters')->restrictOnDelete();
            $table->foreign('diagnosis_id')->references('id')->on('diagnoses')->restrictOnDelete();
            $table->index('encounter_id');
        });

        Schema::create('encounter_procedures', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('encounter_id', 26);
            $table->char('procedure_id', 26);
            // Salinan katalog saat tindakan dicatat: perubahan tarif atau nama di katalog tidak mengubah data ini (MST-08).
            $table->string('procedure_code', 30);
            $table->string('procedure_name');
            $table->string('scope', 20);
            $table->unsignedTinyInteger('tooth_fdi')->nullable();
            $table->string('surfaces', 5)->nullable();
            $table->string('jaw', 10)->nullable();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price_amount');
            $table->string('notes', 500)->nullable();
            $table->char('performed_by', 26);
            $table->dateTime('performed_at');
            $this->standard($table);

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('encounter_id')->references('id')->on('encounters')->restrictOnDelete();
            $table->foreign('procedure_id')->references('id')->on('procedures')->restrictOnDelete();
            $table->foreign('performed_by')->references('id')->on('users')->restrictOnDelete();
            $table->index('encounter_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounter_procedures');
        Schema::dropIfExists('encounter_diagnoses');
        Schema::dropIfExists('clinical_notes');
        Schema::dropIfExists('encounters');
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

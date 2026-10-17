<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// RM-09 dan RM-10: dokumen klinis yang diterbitkan (informed consent, surat keterangan, surat rujukan). Hanya-tambah: isi
// disalin saat diterbitkan sehingga cetak ulang selalu sama; koreksi berarti menerbitkan dokumen baru (M5, M7).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_documents', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('patient_id', 26);
            $table->char('encounter_id', 26)->nullable();
            $table->string('type', 12); // consent, certificate, referral
            $table->char('template_id', 26)->nullable();
            $table->string('number', 40);
            $table->string('title', 150);
            $table->text('body');
            $table->char('doctor_id', 26);
            $table->string('doctor_name');
            $table->string('doctor_sip', 100)->nullable();
            $table->char('issued_by', 26);
            $table->dateTime('issued_at');
            $table->dateTime('created_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('patient_id')->references('id')->on('patients')->restrictOnDelete();
            $table->foreign('encounter_id')->references('id')->on('encounters')->restrictOnDelete();
            $table->foreign('template_id')->references('id')->on('document_templates')->restrictOnDelete();
            $table->foreign('doctor_id')->references('id')->on('doctors')->restrictOnDelete();
            $table->foreign('issued_by')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['clinic_id', 'number']);
            $table->index(['patient_id', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_documents');
    }
};

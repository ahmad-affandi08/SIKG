<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// P2-07: bukti tanda tangan digital pada informed consent. Hanya-tambah dan satu per dokumen: PDF bertanda tangan tersimpan di
// disk privat (dienkripsi bila diatur) dan sidik jari isi dokumen dan PDF dicatat untuk pembuktian (M5, M6).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_signatures', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('document_id', 26);
            $table->char('patient_id', 26);
            $table->string('signer_name', 150);
            $table->string('signer_role', 10); // patient, guardian
            $table->string('witness_name', 150)->nullable();
            $table->string('pdf_path', 255);
            $table->boolean('pdf_encrypted')->default(false);
            $table->char('pdf_sha256', 64);
            $table->char('body_sha256', 64);
            $table->char('signed_by', 26);
            $table->dateTime('signed_at');
            $table->dateTime('created_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('document_id')->references('id')->on('clinical_documents')->restrictOnDelete();
            $table->foreign('patient_id')->references('id')->on('patients')->restrictOnDelete();
            $table->foreign('signed_by')->references('id')->on('users')->restrictOnDelete();
            $table->unique('document_id');
            $table->index(['patient_id', 'signed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_signatures');
    }
};

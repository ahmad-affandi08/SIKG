<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// RM-08: foto klinis, rontgen, dan dokumen pada kunjungan atau gigi. Berkas disimpan di disk privat dan hanya dilayani
// lewat controller berizin; nama berkas asli tidak disimpan karena dapat memuat data pasien.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_files', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('patient_id', 26);
            $table->char('encounter_id', 26);
            $table->unsignedTinyInteger('tooth_fdi')->nullable();
            $table->string('disk', 20);
            $table->string('path');
            $table->string('mime', 50);
            $table->unsignedInteger('size');
            $table->string('kind', 10); // xray, photo, document
            $table->boolean('encrypted')->default(false);
            $table->string('caption', 255)->nullable();
            $table->char('uploaded_by', 26);
            $table->char('created_by', 26)->nullable();
            $table->char('updated_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('patient_id')->references('id')->on('patients')->restrictOnDelete();
            $table->foreign('encounter_id')->references('id')->on('encounters')->restrictOnDelete();
            $table->foreign('uploaded_by')->references('id')->on('users')->restrictOnDelete();
            $table->index(['patient_id', 'created_at']);
            $table->index('encounter_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_files');
    }
};

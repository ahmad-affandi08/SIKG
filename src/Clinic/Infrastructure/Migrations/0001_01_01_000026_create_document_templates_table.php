<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// RM-09 dan RM-10: templat informed consent (per jenis tindakan), surat keterangan, dan surat rujukan yang diatur Admin.
// Tanpa templat aktif, aplikasi memakai templat bawaan.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->string('type', 12); // consent, certificate, referral
            $table->string('name', 150);
            $table->char('procedure_id', 26)->nullable(); // consent: berlaku untuk tindakan ini
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->char('created_by', 26)->nullable();
            $table->char('updated_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('procedure_id')->references('id')->on('procedures')->restrictOnDelete();
            $table->index(['clinic_id', 'type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};

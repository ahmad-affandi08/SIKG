<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// P2-03: pengingat pasien (janji H-1, hari-H, dan kontrol berkala). Pesan tidak disimpan: dirakit saat ditampilkan atau dikirim dari
// templat dan data terkini. Token penyedia API dienkripsi di aplikasi (M13); nomor HP tidak pernah disalin ke tabel ini (M11).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_settings', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->boolean('enabled')->default(false);
            $table->string('mode', 6)->default('link'); // link = tautan WhatsApp dibuka petugas, api = kirim lewat WhatsApp Business API
            $table->boolean('h1_enabled')->default(true);
            $table->boolean('h0_enabled')->default(true);
            $table->boolean('recall_enabled')->default(true);
            $table->unsignedTinyInteger('recall_months')->default(6);
            $table->text('h1_template')->nullable();
            $table->text('h0_template')->nullable();
            $table->text('recall_template')->nullable();
            $table->string('api_phone_number_id', 64)->nullable();
            $table->text('api_token_encrypted')->nullable();
            $table->string('api_template_h1', 100)->nullable();
            $table->string('api_template_h0', 100)->nullable();
            $table->string('api_template_recall', 100)->nullable();
            $table->string('api_language', 10)->default('id');
            $table->char('created_by', 26)->nullable();
            $table->char('updated_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->unique('clinic_id');
        });

        Schema::create('patient_reminders', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('patient_id', 26);
            $table->char('appointment_id', 26)->nullable();
            $table->char('encounter_id', 26)->nullable();
            $table->string('kind', 8); // h1, h0, recall
            $table->string('status', 8); // pending, sent, failed, skipped
            $table->string('channel', 6)->nullable(); // link, api
            $table->dateTime('due_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('last_error', 255)->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->char('sent_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('patient_id')->references('id')->on('patients')->restrictOnDelete();
            $table->foreign('appointment_id')->references('id')->on('appointments')->restrictOnDelete();
            $table->foreign('encounter_id')->references('id')->on('encounters')->restrictOnDelete();
            $table->unique(['kind', 'appointment_id']);
            $table->unique(['kind', 'encounter_id']);
            $table->index(['status', 'due_at']);
        });

        Schema::create('reminder_optouts', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('patient_id', 26);
            $table->boolean('is_active')->default(true); // false = pasien diingatkan kembali
            $table->char('created_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('patient_id')->references('id')->on('patients')->restrictOnDelete();
            $table->unique('patient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_optouts');
        Schema::dropIfExists('patient_reminders');
        Schema::dropIfExists('reminder_settings');
    }
};

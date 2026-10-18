<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // P3-01: pengaturan booking online per klinik. `slug` unik global karena halaman publik dicari dari alamatnya sebelum klinik diketahui.
        Schema::create('booking_settings', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->boolean('enabled')->default(false);
            $table->string('slug', 60)->nullable()->unique();
            $table->unsignedSmallInteger('slot_minutes')->default(30);
            $table->unsignedSmallInteger('horizon_days')->default(30);
            $table->unsignedSmallInteger('min_lead_hours')->default(2);
            $table->unsignedSmallInteger('hold_hours')->default(24);
            $table->unsignedSmallInteger('max_active_per_phone')->default(2);
            $table->string('otp_template', 100)->nullable();
            $table->string('welcome_text', 500)->nullable();
            $table->char('updated_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->unique('clinic_id');
        });

        // Permintaan booking dari halaman publik. Nomor HP terenkripsi dengan blind index untuk batas per nomor (M11); token status
        // disimpan sebagai hash sehingga alamat status tidak dapat ditebak dan tidak memuat data pasien.
        Schema::create('booking_requests', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('token_hash', 64)->unique();
            $table->char('doctor_id', 26);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('patient_name', 120);
            $table->text('phone_encrypted');
            $table->char('phone_hash', 64);
            $table->string('complaint', 300)->nullable();
            $table->string('status', 20);
            $table->boolean('verified')->default(false);
            $table->dateTime('verified_at')->nullable();
            $table->char('code_hash', 64)->nullable();
            $table->dateTime('code_expires_at')->nullable();
            $table->unsignedTinyInteger('code_attempts')->default(0);
            $table->unsignedTinyInteger('code_sent_count')->default(0);
            $table->char('patient_id', 26)->nullable();
            $table->char('appointment_id', 26)->nullable();
            $table->char('decided_by', 26)->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->string('decision_note', 255)->nullable();
            $table->dateTime('expires_at');
            $table->char('ip_hash', 64)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('doctor_id')->references('id')->on('doctors')->restrictOnDelete();
            $table->foreign('patient_id')->references('id')->on('patients')->restrictOnDelete();
            $table->foreign('appointment_id')->references('id')->on('appointments')->restrictOnDelete();
            $table->index(['clinic_id', 'status', 'starts_at']);
            $table->index(['doctor_id', 'starts_at']);
            $table->index(['phone_hash', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_requests');
        Schema::dropIfExists('booking_settings');
    }
};

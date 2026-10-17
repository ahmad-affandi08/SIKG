<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// JDW-07: kontak pengingat janji per pasien (email, Telegram) beserta persetujuan menerima pengingat. Email dan ID obrolan
// Telegram dienkripsi di aplikasi (M11). Tautan Telegram hanya disimpan sebagai hash dan kedaluwarsa.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_contacts', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('patient_id', 26);
            $table->text('email_encrypted')->nullable();
            $table->text('telegram_chat_id_encrypted')->nullable();
            $table->boolean('reminders_opt_in')->default(false);
            $table->dateTime('opt_in_at')->nullable();
            $table->char('telegram_link_hash', 64)->nullable();
            $table->dateTime('telegram_link_expires_at')->nullable();
            $table->char('created_by', 26)->nullable();
            $table->char('updated_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('patient_id')->references('id')->on('patients')->restrictOnDelete();
            $table->unique('patient_id');
            $table->index('telegram_link_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_contacts');
    }
};

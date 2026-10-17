<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// JDW-07: catatan pengingat yang sudah dikirim per janji, jenis (h1 = sehari sebelumnya, h0 = hari kunjungan), dan saluran.
// Kunci unik membuat pengiriman idempoten sehingga cron yang berjalan ulang tidak mengirim dua kali. Tanpa isi pesan dan tujuan.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_reminders', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('appointment_id', 26);
            $table->string('kind', 5);
            $table->string('channel', 10);
            $table->string('status', 10);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('last_attempt_at')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('appointment_id')->references('id')->on('appointments')->restrictOnDelete();
            $table->unique(['appointment_id', 'kind', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_reminders');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Jejak audit (M10). Aplikasi hanya meng-INSERT; tidak ada ubah atau hapus. Tanpa updated_at dan deleted_at.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26)->nullable();
            $table->char('branch_id', 26)->nullable();
            $table->char('actor_id', 26)->nullable();
            $table->string('action', 100);
            $table->string('subject_type', 100);
            $table->string('subject_id', 26);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->dateTime('created_at');

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('actor_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['subject_type', 'subject_id']);
            $table->index(['actor_id', 'created_at']);
            $table->index(['clinic_id', 'created_at']);
        });

        Schema::create('access_logs', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26)->nullable();
            $table->char('branch_id', 26)->nullable();
            $table->char('actor_id', 26)->nullable();
            $table->char('patient_id', 26)->nullable();
            $table->string('route', 150);
            $table->string('ip', 45)->nullable();
            $table->dateTime('at');

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('actor_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['patient_id', 'at']);
            $table->index(['actor_id', 'at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_logs');
        Schema::dropIfExists('audit_logs');
    }
};

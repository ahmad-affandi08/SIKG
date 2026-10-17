<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Berkas laporan yang dibuat lewat antrean (LAP, SYS-07): menunggu, berjalan, selesai, gagal. Baris tidak dihapus; hanya
// berkas di disk privat yang dilepas setelah masa simpan (file_path dikosongkan).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_jobs', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->string('report_key', 30);
            $table->string('format', 8);
            $table->string('status', 10); // queued, running, done, failed
            $table->string('scope', 8); // full, own
            $table->json('filters');
            $table->char('requested_by', 26);
            $table->string('file_path', 255)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->string('error_message', 255)->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('requested_by')->references('id')->on('users')->restrictOnDelete();
            $table->index(['requested_by', 'created_at']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_jobs');
    }
};

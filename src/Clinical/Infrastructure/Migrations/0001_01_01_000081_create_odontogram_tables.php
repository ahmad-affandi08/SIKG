<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ODO-01 sampai ODO-04: satu snapshot odontogram per kunjungan berisi perubahan yang dicatat pada kunjungan itu.
// Odontogram terkini dihitung dari keadaan terbaru tiap pasangan gigi dan permukaan lintas snapshot (M8).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odontogram_snapshots', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('patient_id', 26);
            $table->char('encounter_id', 26);
            $table->string('dentition', 10); // permanent, primary, mixed
            $table->dateTime('taken_at');
            $table->char('created_by', 26)->nullable();
            $table->char('updated_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('patient_id')->references('id')->on('patients')->restrictOnDelete();
            $table->foreign('encounter_id')->references('id')->on('encounters')->restrictOnDelete();
            $table->unique('encounter_id');
            $table->index(['patient_id', 'taken_at']);
        });

        Schema::create('odontogram_tooth_states', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('snapshot_id', 26);
            $table->unsignedTinyInteger('tooth_fdi');
            $table->char('surface', 1); // M, D, O, B, L, W (seluruh gigi)
            $table->string('condition_code', 10);
            $table->char('set_by', 26)->nullable();
            $table->dateTime('set_at');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('snapshot_id')->references('id')->on('odontogram_snapshots')->restrictOnDelete();
            $table->unique(['snapshot_id', 'tooth_fdi', 'surface']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odontogram_tooth_states');
        Schema::dropIfExists('odontogram_snapshots');
    }
};

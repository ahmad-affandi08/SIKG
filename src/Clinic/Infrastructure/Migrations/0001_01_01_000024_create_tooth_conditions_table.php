<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Src\Clinic\Infrastructure\ToothConditionDefaults;

// ODO-03: daftar kondisi gigi yang dapat diatur Admin; warna dan kode dibaca antarmuka dari sini, bukan konstanta (M8).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tooth_conditions', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->string('code', 10);
            $table->string('name', 100);
            $table->char('color', 7);
            $table->string('scope', 10); // surface, tooth, both
            $table->boolean('is_active')->default(true);
            $table->char('created_by', 26)->nullable();
            $table->char('updated_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->unique(['clinic_id', 'code']);
        });

        // Klinik yang sudah ada mendapat daftar bawaan; klinik baru mendapatkannya saat dibuat.
        foreach (DB::table('clinics')->pluck('id') as $clinicId) {
            ToothConditionDefaults::seed((string) $clinicId);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tooth_conditions');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// RM-06: rencana perawatan multi-kunjungan. Status rencana diturunkan dari status langkah; tarif disalin ke langkah saat dibuat
// (estimasi tidak berubah bila katalog berubah, MST-08). Tidak ada hapus permanen: langkah dan rencana dihapus lunak (M5).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_plans', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('patient_id', 26);
            $table->string('title', 150);
            $table->string('status', 12); // planned, in_progress, done, cancelled
            $table->string('notes', 1000)->nullable();
            $this->standard($table);

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('patient_id')->references('id')->on('patients')->restrictOnDelete();
            $table->index(['patient_id', 'status']);
        });

        Schema::create('treatment_plan_items', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('plan_id', 26);
            $table->unsignedSmallInteger('sequence');
            $table->char('procedure_id', 26);
            $table->string('procedure_code', 30);
            $table->string('procedure_name');
            $table->unsignedTinyInteger('tooth_fdi')->nullable();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price_amount'); // salinan tarif saat langkah dibuat
            $table->unsignedBigInteger('estimate_amount'); // jumlah x tarif, diturunkan oleh domain
            $table->string('status', 12); // planned, in_progress, done
            $table->string('notes', 500)->nullable();
            $table->char('encounter_id', 26)->nullable(); // kunjungan tempat langkah dikerjakan
            $table->dateTime('completed_at')->nullable();
            $this->standard($table);

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('plan_id')->references('id')->on('treatment_plans')->restrictOnDelete();
            $table->foreign('procedure_id')->references('id')->on('procedures')->restrictOnDelete();
            $table->foreign('encounter_id')->references('id')->on('encounters')->restrictOnDelete();
            $table->index(['plan_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_plan_items');
        Schema::dropIfExists('treatment_plans');
    }

    private function standard(Blueprint $table): void
    {
        $table->char('created_by', 26)->nullable();
        $table->char('updated_by', 26)->nullable();
        $table->dateTime('created_at')->nullable();
        $table->dateTime('updated_at')->nullable();
        $table->dateTime('deleted_at')->nullable();
    }
};

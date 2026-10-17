<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Master data klinik: dokter, jadwal praktik, kursi, tindakan, metode pembayaran (docs/rules/database.md).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctors', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('user_id', 26)->nullable();
            $table->string('name');
            $table->string('sip_no', 100)->nullable();
            $table->string('specialty', 100)->nullable();
            // Skema jasa dokter (MST-02): {"default":{"mode":"percentage|fixed","value":3000},"overrides":{"<kode tindakan>":{...}}}; persentase dalam basis poin, nominal dalam rupiah
            $table->json('fee_scheme')->nullable();
            $table->boolean('is_active')->default(true);
            $this->standard($table);

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['clinic_id', 'is_active']);
        });

        Schema::create('doctor_schedules', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('doctor_id', 26);
            $table->unsignedTinyInteger('weekday');
            $table->time('start_time');
            $table->time('end_time');
            $this->standard($table);

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('doctor_id')->references('id')->on('doctors')->restrictOnDelete();
            $table->index(['doctor_id', 'weekday']);
        });

        Schema::create('chairs', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $this->standard($table);

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->index(['clinic_id', 'is_active']);
        });

        Schema::create('procedures', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->string('code', 30);
            $table->string('name');
            $table->string('category', 100)->nullable();
            $table->string('scope', 20); // tooth, surface, jaw, visit
            $table->unsignedBigInteger('default_price_amount');
            $table->boolean('is_active')->default(true);
            $this->standard($table);

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->unique(['clinic_id', 'code']);
            $table->index(['clinic_id', 'is_active']);
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->string('name', 100);
            $table->string('type', 20); // cash, transfer, qris, debit, credit
            $table->boolean('is_active')->default(true);
            $this->standard($table);

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->index(['clinic_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('procedures');
        Schema::dropIfExists('chairs');
        Schema::dropIfExists('doctor_schedules');
        Schema::dropIfExists('doctors');
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

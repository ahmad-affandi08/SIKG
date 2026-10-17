<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// P2-08: kategori dan biaya operasional klinik untuk laporan laba rugi sederhana. Uang BIGINT rupiah (M4). Biaya tidak dihapus
// (M5): yang salah dibatalkan dengan alasan dan tidak masuk laporan.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->char('created_by', 26)->nullable();
            $table->char('updated_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->index(['clinic_id', 'name']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->date('expense_date');
            $table->char('category_id', 26);
            $table->unsignedBigInteger('amount');
            $table->string('payee', 150)->nullable();
            $table->string('description', 255);
            $table->string('reference_no', 60)->nullable();
            $table->string('status', 10); // recorded, void
            $table->string('void_reason', 255)->nullable();
            $table->dateTime('voided_at')->nullable();
            $table->char('voided_by', 26)->nullable();
            $table->char('created_by', 26)->nullable();
            $table->char('updated_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('category_id')->references('id')->on('expense_categories')->restrictOnDelete();
            $table->index(['expense_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};

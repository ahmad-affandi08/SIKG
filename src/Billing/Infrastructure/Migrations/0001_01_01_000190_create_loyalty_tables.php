<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// P3-06: voucher, poin loyalitas, dan paket kunjungan. Uang BIGINT rupiah, poin bilangan bulat (M4). Buku poin hanya bertambah;
// promosi dan penggunaan paket dilepas (status), tidak dihapus (M5). Satu tagihan paling banyak satu promosi aktif.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->string('code', 30);
            $table->string('name', 150);
            $table->string('kind', 10); // percent, amount
            $table->unsignedBigInteger('value'); // persen bulat (jenis persen) atau rupiah (jenis nominal)
            $table->unsignedBigInteger('min_subtotal_amount')->default(0);
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedSmallInteger('max_per_patient')->default(1);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
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

        Schema::create('invoice_promotions', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('invoice_id', 26);
            $table->char('patient_id', 26);
            $table->string('type', 10); // voucher, points
            $table->char('voucher_id', 26)->nullable();
            $table->unsignedInteger('points_used')->default(0);
            $table->unsignedBigInteger('discount_amount');
            $table->string('status', 10); // applied, released
            $table->tinyInteger('active_slot')->nullable(); // 1 selama applied; unik per tagihan
            $table->string('released_reason', 100)->nullable();
            $table->char('created_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('invoice_id')->references('id')->on('invoices')->restrictOnDelete();
            $table->foreign('patient_id')->references('id')->on('patients')->restrictOnDelete();
            $table->foreign('voucher_id')->references('id')->on('vouchers')->restrictOnDelete();
            $table->unique(['invoice_id', 'active_slot']);
            $table->index(['voucher_id', 'status']);
        });

        Schema::create('loyalty_settings', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->boolean('enabled')->default(false);
            $table->unsignedBigInteger('rupiah_per_point')->default(10000); // belanja per 1 poin yang didapat
            $table->unsignedBigInteger('point_value_amount')->default(1000); // nilai rupiah 1 poin saat ditukar
            $table->unsignedInteger('min_redeem_points')->default(10);
            $table->char('updated_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->unique('clinic_id');
        });

        Schema::create('loyalty_balances', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('patient_id', 26);
            $table->integer('points')->default(0);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('patient_id')->references('id')->on('patients')->restrictOnDelete();
            $table->unique('patient_id');
        });

        Schema::create('loyalty_ledger', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('patient_id', 26);
            $table->integer('delta'); // bertanda: dapat positif, tukar negatif
            $table->integer('balance_after');
            $table->string('kind', 10); // earn, redeem, release, reverse, adjust
            $table->char('invoice_id', 26)->nullable();
            $table->string('dedupe_key', 60)->nullable();
            $table->string('note', 255)->nullable();
            $table->char('created_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('patient_id')->references('id')->on('patients')->restrictOnDelete();
            $table->foreign('invoice_id')->references('id')->on('invoices')->restrictOnDelete();
            $table->unique(['clinic_id', 'dedupe_key']);
            $table->index(['patient_id', 'created_at']);
        });

        Schema::create('visit_packages', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->string('name', 150);
            $table->unsignedSmallInteger('sessions');
            $table->unsignedBigInteger('price_amount');
            $table->unsignedBigInteger('max_session_value_amount')->nullable(); // batas nilai yang ditanggung per sesi
            $table->unsignedSmallInteger('valid_days')->nullable();
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

        Schema::create('patient_packages', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('patient_id', 26);
            $table->char('package_id', 26);
            $table->string('name', 150); // salinan saat dibeli (M7)
            $table->unsignedSmallInteger('sessions_total');
            $table->unsignedSmallInteger('sessions_left');
            $table->unsignedBigInteger('price_amount');
            $table->unsignedBigInteger('max_session_value_amount')->nullable();
            $table->date('purchased_on');
            $table->date('expires_on')->nullable();
            $table->char('invoice_id', 26)->nullable(); // tagihan pembelian
            $table->string('status', 10); // active, void
            $table->char('created_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('patient_id')->references('id')->on('patients')->restrictOnDelete();
            $table->foreign('package_id')->references('id')->on('visit_packages')->restrictOnDelete();
            $table->foreign('invoice_id')->references('id')->on('invoices')->restrictOnDelete();
            $table->index(['patient_id', 'status']);
        });

        Schema::create('package_usages', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('patient_package_id', 26);
            $table->char('invoice_id', 26);
            $table->char('item_id', 26);
            $table->unsignedBigInteger('value_amount');
            $table->string('status', 10); // applied, released
            $table->char('created_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('patient_package_id')->references('id')->on('patient_packages')->restrictOnDelete();
            $table->foreign('invoice_id')->references('id')->on('invoices')->restrictOnDelete();
            $table->index(['invoice_id', 'status']);
            $table->index('item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_usages');
        Schema::dropIfExists('patient_packages');
        Schema::dropIfExists('visit_packages');
        Schema::dropIfExists('loyalty_ledger');
        Schema::dropIfExists('loyalty_balances');
        Schema::dropIfExists('loyalty_settings');
        Schema::dropIfExists('invoice_promotions');
        Schema::dropIfExists('vouchers');
    }
};

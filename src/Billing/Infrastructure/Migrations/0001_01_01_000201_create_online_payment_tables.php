<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // P3-05: pengaturan pembayaran online per klinik. Server key hanya tersimpan terenkripsi dan tidak pernah ditampilkan kembali (M13).
        // `webhook_token` (acak) menjadi bagian alamat webhook sehingga klinik dikenali tanpa sesi.
        Schema::create('online_payment_settings', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->boolean('enabled')->default(false);
            $table->string('provider', 20)->default('midtrans');
            $table->string('environment', 12)->default('sandbox');
            $table->string('merchant_id', 60)->nullable();
            $table->text('server_key_encrypted')->nullable();
            $table->char('payment_method_id', 26)->nullable();
            $table->unsignedSmallInteger('expiry_minutes')->default(15);
            $table->char('webhook_token', 40)->unique();
            $table->char('updated_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->restrictOnDelete();
            $table->unique('clinic_id');
        });

        // Satu niat bayar online (QRIS dinamis) untuk satu tagihan. `active_slot` = 1 selama menunggu, sehingga satu tagihan hanya
        // punya satu QR aktif. Pembayaran sebenarnya dicatat lewat alur pembayaran biasa (M7) dan ditautkan di `payment_id`.
        Schema::create('payment_intents', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->char('invoice_id', 26);
            $table->string('order_id', 50)->unique();
            $table->string('provider', 20);
            $table->unsignedBigInteger('amount');
            $table->string('status', 12);
            $table->unsignedTinyInteger('active_slot')->nullable();
            $table->mediumText('qr_image')->nullable();
            $table->text('qr_string')->nullable();
            $table->string('gateway_ref', 100)->nullable();
            $table->dateTime('expires_at');
            $table->dateTime('paid_at')->nullable();
            $table->char('payment_id', 26)->nullable();
            $table->string('note', 255)->nullable();
            $table->dateTime('last_checked_at')->nullable();
            $table->char('created_by', 26)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('invoice_id')->references('id')->on('invoices')->restrictOnDelete();
            $table->foreign('payment_id')->references('id')->on('payments')->restrictOnDelete();
            $table->unique(['invoice_id', 'active_slot']);
            $table->index(['clinic_id', 'status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_intents');
        Schema::dropIfExists('online_payment_settings');
    }
};

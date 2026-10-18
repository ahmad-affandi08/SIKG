<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// P3-05: penyedia pembayaran online diganti dari Midtrans ke DOKU. DOKU memakai Client ID dan Secret Key, dan halaman pembayaran
// (Checkout) dihosting DOKU sehingga yang disimpan adalah tautan halamannya, bukan gambar QR.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('online_payment_settings', function (Blueprint $table) {
            $table->renameColumn('merchant_id', 'client_id');
            $table->renameColumn('server_key_encrypted', 'secret_key_encrypted');
        });
        Schema::table('online_payment_settings', function (Blueprint $table) {
            $table->string('client_id', 100)->nullable()->change();
            $table->string('provider', 20)->default('doku')->change();
        });
        Schema::table('payment_intents', function (Blueprint $table) {
            $table->dropColumn('qr_image');
            $table->renameColumn('qr_string', 'payment_url');
        });
    }

    public function down(): void
    {
        Schema::table('payment_intents', function (Blueprint $table) {
            $table->renameColumn('payment_url', 'qr_string');
            $table->mediumText('qr_image')->nullable();
        });
        Schema::table('online_payment_settings', function (Blueprint $table) {
            $table->renameColumn('client_id', 'merchant_id');
            $table->renameColumn('secret_key_encrypted', 'server_key_encrypted');
        });
    }
};

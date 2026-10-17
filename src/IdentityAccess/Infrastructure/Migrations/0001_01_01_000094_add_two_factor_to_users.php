<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// AUTH-05: verifikasi dua langkah (TOTP) opsional untuk Pemilik dan Admin. Rahasia dienkripsi di aplikasi; kode pemulihan
// hanya berupa hash. `two_factor_last_step` mencegah satu kode dipakai dua kali.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret_encrypted')->nullable()->after('password_changed_at');
            $table->text('two_factor_pending_encrypted')->nullable()->after('two_factor_secret_encrypted');
            $table->dateTime('two_factor_confirmed_at')->nullable()->after('two_factor_pending_encrypted');
            $table->unsignedBigInteger('two_factor_last_step')->default(0)->after('two_factor_confirmed_at');
            $table->json('two_factor_recovery_hashes')->nullable()->after('two_factor_last_step');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret_encrypted', 'two_factor_pending_encrypted', 'two_factor_confirmed_at', 'two_factor_last_step', 'two_factor_recovery_hashes']);
        });
    }
};

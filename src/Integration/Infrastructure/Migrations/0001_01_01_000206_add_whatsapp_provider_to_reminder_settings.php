<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Saluran WhatsApp terpadu (menggantikan email dan Telegram): pilihan penyedia (WhatsApp Resmi/Meta atau penyedia tidak resmi),
// alamat dasar API (Wablas, WAHA), sesi (WAHA), dan nama templat Meta untuk pemberitahuan staf. Kolom api_* lama tetap dipakai
// (ID nomor telepon, token terenkripsi, bahasa). Pengaturan yang sudah memakai mode `api` berarti WhatsApp Resmi (Meta).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reminder_settings', function (Blueprint $table) {
            $table->string('provider', 12)->default('link')->after('mode'); // link, meta, fonnte, wablas, waha
            $table->string('api_base_url', 255)->nullable()->after('api_phone_number_id');
            $table->string('api_session', 64)->nullable()->after('api_base_url');
            $table->string('staff_template', 100)->nullable()->after('api_language');
        });

        DB::table('reminder_settings')->where('mode', 'api')->update(['provider' => 'meta']);
    }

    public function down(): void
    {
        Schema::table('reminder_settings', function (Blueprint $table) {
            $table->dropColumn(['provider', 'api_base_url', 'api_session', 'staff_template']);
        });
    }
};

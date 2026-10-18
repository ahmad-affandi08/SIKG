<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// P3-01: batas permintaan booking baru per jam untuk seluruh klinik, menahan bot yang memborong slot atau menguras kuota WhatsApp.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_requests_per_hour')->default(30)->after('max_active_per_phone');
        });
    }

    public function down(): void
    {
        Schema::table('booking_settings', function (Blueprint $table) {
            $table->dropColumn('max_requests_per_hour');
        });
    }
};

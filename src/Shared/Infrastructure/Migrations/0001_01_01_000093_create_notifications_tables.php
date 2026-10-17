<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SYS-05: pemberitahuan dalam aplikasi untuk staf. Satu baris per pemberitahuan, ditujukan ke pemegang izin tertentu; status
// dibaca disimpan per pengguna. Tanpa PHI (isinya juga dapat diteruskan ke email dan Telegram klinik). Bukan data klinis atau
// keuangan: baris lama dipangkas berkala oleh `sikg:notifications:prune`.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('clinic_id', 26);
            $table->char('branch_id', 26)->nullable();
            $table->string('kind', 40);
            $table->string('title', 150);
            $table->string('body', 500);
            $table->string('link', 255)->nullable();
            $table->string('permission', 60);
            $table->string('dedupe_key', 120);
            $table->dateTime('created_at');

            $table->foreign('clinic_id')->references('id')->on('clinics')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->unique(['clinic_id', 'dedupe_key']);
            $table->index(['clinic_id', 'created_at']);
        });

        Schema::create('notification_reads', function (Blueprint $table) {
            $table->char('notification_id', 26);
            $table->char('user_id', 26);
            $table->dateTime('read_at');

            $table->primary(['notification_id', 'user_id']);
            $table->foreign('notification_id')->references('id')->on('notifications')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_reads');
        Schema::dropIfExists('notifications');
    }
};

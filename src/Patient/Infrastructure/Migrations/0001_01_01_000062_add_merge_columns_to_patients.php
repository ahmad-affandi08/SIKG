<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PAS-07: pasien ganda yang digabung tetap ada (diarsipkan) dan menunjuk pasien utama; tidak ada yang dihapus.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->char('merged_into_id', 26)->nullable();
            $table->dateTime('merged_at')->nullable();

            $table->foreign('merged_into_id')->references('id')->on('patients')->restrictOnDelete();
            $table->index('merged_into_id');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropForeign(['merged_into_id']);
            $table->dropIndex(['merged_into_id']);
            $table->dropColumn(['merged_into_id', 'merged_at']);
        });
    }
};

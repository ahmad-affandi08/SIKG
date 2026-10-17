<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// RM-06: tindakan yang dikerjakan dari rencana perawatan menyimpan penunjuk ke langkahnya.
// ODO-05: kode kondisi hasil tindakan disalin saat tindakan dicatat (MST-08) dan status pembaruan odontogram menunggu
// konfirmasi dokter (kosong = menunggu, applied = diterapkan, dismissed = diabaikan).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encounter_procedures', function (Blueprint $table) {
            $table->char('plan_item_id', 26)->nullable();
            $table->string('result_condition_code', 10)->nullable();
            $table->string('odontogram_status', 10)->nullable();

            $table->foreign('plan_item_id')->references('id')->on('treatment_plan_items')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('encounter_procedures', function (Blueprint $table) {
            $table->dropForeign(['plan_item_id']);
            $table->dropColumn(['plan_item_id', 'result_condition_code', 'odontogram_status']);
        });
    }
};

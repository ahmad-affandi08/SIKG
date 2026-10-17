<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// P2-01: kode ICD-9-CM tindakan untuk pelaporan prosedur ke SATUSEHAT. Kosong berarti tindakan tidak dikirim sebagai prosedur.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procedures', function (Blueprint $table) {
            $table->string('icd9_code', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('procedures', function (Blueprint $table) {
            $table->dropColumn('icd9_code');
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ODO-05: tindakan dapat menghasilkan kondisi gigi (contoh: tambal menjadi Tambalan). Kode kondisi dari tooth_conditions,
// diatur Admin di katalog tindakan; kosong berarti tindakan tidak mengubah odontogram.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procedures', function (Blueprint $table) {
            $table->string('result_condition_code', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('procedures', function (Blueprint $table) {
            $table->dropColumn('result_condition_code');
        });
    }
};

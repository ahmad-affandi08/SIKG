<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// MST-01: header kwitansi dan resep.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->text('receipt_header')->nullable()->after('logo_path');
            $table->text('prescription_header')->nullable()->after('receipt_header');
        });
    }

    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn(['receipt_header', 'prescription_header']);
        });
    }
};

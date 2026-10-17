<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Src\Clinical\Infrastructure\Reference\Icd10DentalCatalog;

// MST-07: referensi ICD-10 bersama semua klinik (tanpa clinic_id): data rujukan, bukan data transaksi.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnoses', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('icd10_code', 10)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->index('name');
        });

        $now = now();
        foreach (Icd10DentalCatalog::all() as $code => $name) {
            DB::table('diagnoses')->insertOrIgnore([
                'id' => strtoupper((string) Str::ulid()),
                'icd10_code' => $code,
                'name' => $name,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnoses');
    }
};

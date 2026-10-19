<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Clinical\Infrastructure\Reference\Icd10GeneralCatalog;

// KLU-03: diagnosa lazim layanan primer untuk klinik umum. Referensi bersama semua klinik; kode yang sudah ada tidak ditimpa
// dan tidak ada yang dihapus (tabel diagnoses ditautkan RESTRICT dari kunjungan).
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach (Icd10GeneralCatalog::all() as $code => $name) {
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
        // Sengaja kosong: kode yang mungkin sudah dipakai kunjungan tidak dihapus (M5).
    }
};

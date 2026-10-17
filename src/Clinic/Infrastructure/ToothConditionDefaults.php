<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Kondisi gigi bawaan (ODO-03): sehat, karies, tambalan, mahkota, perawatan saluran akar, impaksi, hilang, sisa akar,
 * implan, gigi tiruan atau jembatan, dan lainnya. Admin dapat menambah, mengubah warna dan nama, atau menonaktifkan.
 */
final class ToothConditionDefaults
{
    /**
     * @return list<array{code: string, name: string, color: string, scope: string}>
     */
    public static function all(): array
    {
        return [
            ['code' => 'SEH', 'name' => 'Sehat', 'color' => '#FFFFFF', 'scope' => 'both'],
            ['code' => 'KAR', 'name' => 'Karies', 'color' => '#DC2626', 'scope' => 'surface'],
            ['code' => 'TAM', 'name' => 'Tambalan', 'color' => '#2563EB', 'scope' => 'surface'],
            ['code' => 'MHK', 'name' => 'Mahkota', 'color' => '#CA8A04', 'scope' => 'tooth'],
            ['code' => 'PSA', 'name' => 'Perawatan saluran akar', 'color' => '#7C3AED', 'scope' => 'tooth'],
            ['code' => 'IMP', 'name' => 'Impaksi', 'color' => '#EA580C', 'scope' => 'tooth'],
            ['code' => 'HLG', 'name' => 'Hilang', 'color' => '#475569', 'scope' => 'tooth'],
            ['code' => 'SIS', 'name' => 'Sisa akar', 'color' => '#92400E', 'scope' => 'tooth'],
            ['code' => 'IMN', 'name' => 'Implan', 'color' => '#0D9488', 'scope' => 'tooth'],
            ['code' => 'GTP', 'name' => 'Gigi tiruan atau jembatan', 'color' => '#DB2777', 'scope' => 'tooth'],
            ['code' => 'LNY', 'name' => 'Lainnya', 'color' => '#9CA3AF', 'scope' => 'both'],
        ];
    }

    /** Menambahkan kondisi bawaan yang belum ada untuk klinik itu. */
    public static function seed(string $clinicId): void
    {
        $now = now();
        foreach (self::all() as $c) {
            DB::table('tooth_conditions')->insertOrIgnore($c + [
                'id' => strtoupper((string) Str::ulid()),
                'clinic_id' => $clinicId,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}

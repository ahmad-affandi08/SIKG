<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Katalog tindakan gigi umum (MST-04) sebagai usulan awal. Semua baris dibuat NONAKTIF dengan tarif 0: klinik wajib
 * mengisi tarif, lalu mengaktifkannya, sebelum tindakan dapat dicatat dan ditagih. Tarif sengaja tidak diisi karena berbeda
 * di tiap klinik. Kondisi odontogram hasil tindakan (ODO-05) sudah terisi. Kode ICD-9-CM (SATUSEHAT) hanya diisi untuk
 * tindakan yang padanannya jelas dan wajib diperiksa petugas rekam medis; sisanya dikosongkan (tidak ikut dikirim).
 * Aman dijalankan berulang: kode yang sudah ada tidak diduplikasi dan tidak ditimpa.
 */
final class ProcedureSeeder extends Seeder
{
    /**
     * @return list<array{code: string, name: string, category: string, scope: string, result: ?string, icd9: ?string}>
     */
    public static function catalog(): array
    {
        return [
            ['code' => 'KONSUL', 'name' => 'Konsultasi dan pemeriksaan', 'category' => 'Umum', 'scope' => 'visit', 'result' => null, 'icd9' => null],
            ['code' => 'KONTROL', 'name' => 'Kontrol ulang', 'category' => 'Umum', 'scope' => 'visit', 'result' => null, 'icd9' => null],
            ['code' => 'SCALING', 'name' => 'Scaling (pembersihan karang gigi)', 'category' => 'Periodonsia', 'scope' => 'jaw', 'result' => null, 'icd9' => '96.54'],
            ['code' => 'POLISH', 'name' => 'Poles gigi', 'category' => 'Periodonsia', 'scope' => 'jaw', 'result' => null, 'icd9' => null],
            ['code' => 'FLUOR', 'name' => 'Aplikasi fluor', 'category' => 'Pencegahan', 'scope' => 'jaw', 'result' => null, 'icd9' => null],
            ['code' => 'SEALANT', 'name' => 'Pit dan fissure sealant', 'category' => 'Pencegahan', 'scope' => 'tooth', 'result' => null, 'icd9' => null],
            ['code' => 'TAMBAL-KOM', 'name' => 'Tambal komposit', 'category' => 'Konservasi', 'scope' => 'surface', 'result' => 'TAM', 'icd9' => '23.2'],
            ['code' => 'TAMBAL-GIC', 'name' => 'Tambal semen ionomer kaca (GIC)', 'category' => 'Konservasi', 'scope' => 'surface', 'result' => 'TAM', 'icd9' => '23.2'],
            ['code' => 'TAMBAL-SEM', 'name' => 'Tambal sementara', 'category' => 'Konservasi', 'scope' => 'surface', 'result' => null, 'icd9' => null],
            ['code' => 'PULPOTOMI', 'name' => 'Pulpotomi', 'category' => 'Endodonsia', 'scope' => 'tooth', 'result' => null, 'icd9' => null],
            ['code' => 'PSA', 'name' => 'Perawatan saluran akar', 'category' => 'Endodonsia', 'scope' => 'tooth', 'result' => 'PSA', 'icd9' => '23.70'],
            ['code' => 'CABUT-SULUNG', 'name' => 'Cabut gigi sulung', 'category' => 'Bedah mulut', 'scope' => 'tooth', 'result' => 'HLG', 'icd9' => '23.01'],
            ['code' => 'CABUT-DEWASA', 'name' => 'Cabut gigi dewasa', 'category' => 'Bedah mulut', 'scope' => 'tooth', 'result' => 'HLG', 'icd9' => '23.09'],
            ['code' => 'CABUT-BEDAH', 'name' => 'Cabut gigi dengan pembedahan', 'category' => 'Bedah mulut', 'scope' => 'tooth', 'result' => 'HLG', 'icd9' => '23.19'],
            ['code' => 'SISA-AKAR', 'name' => 'Pengambilan sisa akar', 'category' => 'Bedah mulut', 'scope' => 'tooth', 'result' => 'HLG', 'icd9' => '23.11'],
            ['code' => 'INSISI-ABSES', 'name' => 'Insisi abses', 'category' => 'Bedah mulut', 'scope' => 'visit', 'result' => null, 'icd9' => null],
            ['code' => 'MAHKOTA', 'name' => 'Pemasangan mahkota', 'category' => 'Prostodonsia', 'scope' => 'tooth', 'result' => 'MHK', 'icd9' => '23.41'],
            ['code' => 'JEMBATAN', 'name' => 'Pemasangan gigi tiruan jembatan', 'category' => 'Prostodonsia', 'scope' => 'tooth', 'result' => 'GTP', 'icd9' => '23.42'],
            ['code' => 'GIGITIRUAN', 'name' => 'Gigi tiruan lepasan', 'category' => 'Prostodonsia', 'scope' => 'jaw', 'result' => null, 'icd9' => null],
            ['code' => 'BLEACHING', 'name' => 'Pemutihan gigi (bleaching)', 'category' => 'Estetik', 'scope' => 'jaw', 'result' => null, 'icd9' => null],
            ['code' => 'RO-PERIAPIKAL', 'name' => 'Foto rontgen periapikal', 'category' => 'Radiologi', 'scope' => 'tooth', 'result' => null, 'icd9' => '87.12'],
            ['code' => 'RO-PANORAMIK', 'name' => 'Foto rontgen panoramik', 'category' => 'Radiologi', 'scope' => 'visit', 'result' => null, 'icd9' => null],
        ];
    }

    public function run(): void
    {
        foreach (DB::table('clinics')->pluck('id') as $clinicId) {
            $this->seedClinic((string) $clinicId);
        }
    }

    /** @return int jumlah tindakan yang ditambahkan */
    public function seedClinic(string $clinicId): int
    {
        $existing = DB::table('procedures')->where('clinic_id', $clinicId)->pluck('code')
            ->map(fn ($c) => mb_strtolower((string) $c))->all();
        $conditions = DB::table('tooth_conditions')->where('clinic_id', $clinicId)->pluck('code')->all();
        $added = 0;
        $now = now();
        foreach (self::catalog() as $row) {
            if (in_array(mb_strtolower($row['code']), $existing, true)) {
                continue;
            }
            DB::table('procedures')->insert([
                'id' => strtoupper((string) Str::ulid()), 'clinic_id' => $clinicId, 'code' => $row['code'], 'name' => $row['name'],
                'category' => $row['category'], 'scope' => $row['scope'], 'default_price_amount' => 0, 'is_active' => false,
                'result_condition_code' => $row['result'] !== null && in_array($row['result'], $conditions, true) ? $row['result'] : null,
                'icd9_code' => $row['icd9'], 'created_at' => $now, 'updated_at' => $now,
            ]);
            $added++;
        }

        return $added;
    }
}

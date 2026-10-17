<?php

declare(strict_types=1);

namespace Src\Clinical\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;

/**
 * Referensi ICD-10 bersama semua klinik (tanpa clinic_id); bukan data pasien.
 *
 * @property string $id
 * @property string $icd10_code
 * @property string $name
 * @property bool $is_active
 */
final class DiagnosisModel extends Model
{
    use UsesUlid;

    protected $table = 'diagnoses';

    /** @var list<string> */
    protected $fillable = ['icd10_code', 'name', 'is_active'];

    /** @var array<string, string> */
    protected $casts = ['is_active' => 'boolean'];
}

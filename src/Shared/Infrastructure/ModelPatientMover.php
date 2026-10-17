<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use RuntimeException;
use Src\Shared\Application\PatientReferenceMover;
use Src\Shared\Application\TenantContext;
use Src\Shared\Infrastructure\Tenancy\ClinicScope;

/**
 * Memindahkan seluruh baris sebuah model bertenant dari satu pasien ke pasien lain (PAS-07). Pasien berlaku di seluruh cabang
 * klinik, jadi batas cabang pengguna diabaikan, tetapi batas klinik tetap dipegang. Baris yang dihapus lunak ikut berpindah
 * agar riwayatnya tidak tertinggal pada pasien ganda. Pembaruan massal tidak menyentuh model event dan tidak menghapus apa pun.
 */
final readonly class ModelPatientMover implements PatientReferenceMover
{
    /**
     * @param  class-string<Model>  $model
     */
    public function __construct(private string $label, private string $model, private TenantContext $tenant) {}

    public function label(): string
    {
        return $this->label;
    }

    public function count(string $patientId): int
    {
        return $this->query($patientId)->count();
    }

    public function move(string $fromPatientId, string $toPatientId): int
    {
        return $this->query($fromPatientId)->update(['patient_id' => $toPatientId]);
    }

    /**
     * @return Builder<Model>
     */
    private function query(string $patientId): Builder
    {
        $clinicId = $this->tenant->clinicId() ?? throw new RuntimeException('Konteks klinik kosong.');

        return $this->model::query()
            ->withoutGlobalScopes([ClinicScope::class, SoftDeletingScope::class])
            ->where('clinic_id', $clinicId)
            ->where('patient_id', $patientId);
    }
}

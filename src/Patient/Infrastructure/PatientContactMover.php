<?php

declare(strict_types=1);

namespace Src\Patient\Infrastructure;

use Illuminate\Database\Eloquent\Builder;
use RuntimeException;
use Src\Patient\Infrastructure\Eloquent\PatientContactModel;
use Src\Shared\Application\PatientReferenceMover;
use Src\Shared\Application\TenantContext;
use Src\Shared\Infrastructure\Tenancy\ClinicScope;

/**
 * Penggabungan pasien (PAS-07) untuk kontak pengingat. Satu pasien hanya punya satu baris kontak, jadi kontak pasien ganda
 * berpindah hanya bila pasien utama belum punya; bila sudah punya, kontak utama dipertahankan dan kontak ganda dikosongkan
 * (saluran dan persetujuan dilepas) agar tidak ada pengingat ke tujuan yang tidak lagi dikelola. Tidak ada baris yang dihapus (M5).
 */
final readonly class PatientContactMover implements PatientReferenceMover
{
    public function __construct(private TenantContext $tenant) {}

    public function label(): string
    {
        return 'Kontak pengingat';
    }

    public function count(string $patientId): int
    {
        return $this->query($patientId)->count();
    }

    public function move(string $fromPatientId, string $toPatientId): int
    {
        $source = $this->query($fromPatientId)->first();
        if ($source === null) {
            return 0;
        }

        if ($this->query($toPatientId)->exists()) {
            $source->forceFill([
                'email_encrypted' => null, 'telegram_chat_id_encrypted' => null, 'reminders_opt_in' => false,
                'opt_in_at' => null, 'telegram_link_hash' => null, 'telegram_link_expires_at' => null,
            ])->save();

            return 0;
        }

        $source->forceFill(['patient_id' => $toPatientId])->save();

        return 1;
    }

    /**
     * @return Builder<PatientContactModel>
     */
    private function query(string $patientId)
    {
        $clinicId = $this->tenant->clinicId() ?? throw new RuntimeException('Konteks klinik kosong.');

        return PatientContactModel::query()->withoutGlobalScope(ClinicScope::class)
            ->where('clinic_id', $clinicId)->where('patient_id', $patientId);
    }
}

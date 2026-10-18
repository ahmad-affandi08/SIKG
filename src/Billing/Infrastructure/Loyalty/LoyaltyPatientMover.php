<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure\Loyalty;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Src\Billing\Infrastructure\Eloquent\LoyaltyBalanceModel;
use Src\Billing\Infrastructure\Eloquent\LoyaltyLedgerModel;
use Src\Shared\Application\PatientReferenceMover;
use Src\Shared\Application\TenantContext;
use Src\Shared\Domain\IdGenerator;
use Src\Shared\Infrastructure\Tenancy\ClinicScope;

/**
 * Penggabungan pasien (PAS-07): buku poin pasien ganda berpindah ke pasien utama dan saldonya dijumlahkan. Hanya `patient_id`
 * baris buku yang berubah (pembaruan massal, tanpa mengubah delta atau saldo-sesudah per baris); tidak ada yang dihapus.
 */
final readonly class LoyaltyPatientMover implements PatientReferenceMover
{
    public function __construct(private TenantContext $tenant, private IdGenerator $ids) {}

    public function label(): string
    {
        return 'Poin loyalitas';
    }

    public function count(string $patientId): int
    {
        return LoyaltyLedgerModel::query()->withoutGlobalScopes([ClinicScope::class])->where('clinic_id', $this->clinic())->where('patient_id', $patientId)->count();
    }

    public function move(string $fromPatientId, string $toPatientId): int
    {
        $clinic = $this->clinic();
        $moved = LoyaltyLedgerModel::query()->withoutGlobalScopes([ClinicScope::class])->where('clinic_id', $clinic)->where('patient_id', $fromPatientId)->update(['patient_id' => $toPatientId]);

        $from = LoyaltyBalanceModel::query()->withoutGlobalScopes([ClinicScope::class])->where('clinic_id', $clinic)->where('patient_id', $fromPatientId)->lockForUpdate()->first();
        if ($from !== null) {
            DB::table('loyalty_balances')->insertOrIgnore(['id' => $this->ids->generate(), 'clinic_id' => $clinic, 'patient_id' => $toPatientId, 'points' => 0, 'created_at' => now(), 'updated_at' => now()]);
            LoyaltyBalanceModel::query()->withoutGlobalScopes([ClinicScope::class])->where('clinic_id', $clinic)->where('patient_id', $toPatientId)->increment('points', $from->points);
            $from->forceFill(['points' => 0])->save();
        }

        return $moved;
    }

    private function clinic(): string
    {
        return $this->tenant->clinicId() ?? throw new RuntimeException('Konteks klinik kosong.');
    }
}

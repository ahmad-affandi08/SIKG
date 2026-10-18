<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure\Loyalty;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Src\Billing\Domain\Loyalty\LoyaltyRepository;
use Src\Billing\Domain\Loyalty\LoyaltySettings;
use Src\Billing\Infrastructure\Eloquent\LoyaltyBalanceModel;
use Src\Billing\Infrastructure\Eloquent\LoyaltyLedgerModel;
use Src\Billing\Infrastructure\Eloquent\LoyaltySettingModel;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TenantContext;
use Src\Shared\Domain\IdGenerator;

final readonly class EloquentLoyaltyRepository implements LoyaltyRepository
{
    public function __construct(private TenantContext $tenant, private IdGenerator $ids, private Clock $clock) {}

    public function settings(): LoyaltySettings
    {
        $m = LoyaltySettingModel::query()->first();

        return $m === null ? LoyaltySettings::disabled() : new LoyaltySettings($m->enabled, $m->rupiah_per_point, $m->point_value_amount, $m->min_redeem_points);
    }

    public function saveSettings(string $actorId, LoyaltySettings $settings): void
    {
        $m = LoyaltySettingModel::query()->first() ?? new LoyaltySettingModel;
        $m->forceFill(['enabled' => $settings->enabled, 'rupiah_per_point' => $settings->rupiahPerPoint, 'point_value_amount' => $settings->pointValueAmount, 'min_redeem_points' => $settings->minRedeemPoints, 'updated_by' => $actorId])->save();
    }

    public function lockedBalance(string $patientId): int
    {
        $clinic = $this->tenant->clinicId() ?? throw new RuntimeException('Konteks klinik kosong.');
        // insertOrIgnore aman terhadap dua proses yang membuat baris saldo bersamaan (unik per pasien).
        DB::table('loyalty_balances')->insertOrIgnore([
            'id' => $this->ids->generate(), 'clinic_id' => $clinic, 'patient_id' => $patientId, 'points' => 0, 'created_at' => $this->clock->now(), 'updated_at' => $this->clock->now(),
        ]);

        return LoyaltyBalanceModel::query()->where('patient_id', $patientId)->lockForUpdate()->firstOrFail()->points;
    }

    public function balance(string $patientId): int
    {
        return (int) LoyaltyBalanceModel::query()->where('patient_id', $patientId)->value('points');
    }

    public function append(string $patientId, int $delta, string $kind, ?string $invoiceId, ?string $dedupeKey, ?string $note, ?string $actorId): bool
    {
        $balance = $this->lockedBalance($patientId);
        if ($dedupeKey !== null && LoyaltyLedgerModel::query()->where('dedupe_key', $dedupeKey)->exists()) {
            return false;
        }
        $after = $balance + $delta;
        LoyaltyBalanceModel::query()->where('patient_id', $patientId)->update(['points' => $after, 'updated_at' => $this->clock->now()]);
        (new LoyaltyLedgerModel)->forceFill([
            'patient_id' => $patientId, 'delta' => $delta, 'balance_after' => $after, 'kind' => $kind, 'invoice_id' => $invoiceId, 'dedupe_key' => $dedupeKey,
            'note' => $note === null ? null : mb_substr($note, 0, 255), 'created_by' => $actorId, 'created_at' => $this->clock->now(),
        ])->save();

        return true;
    }

    public function ledger(string $patientId, int $limit): array
    {
        return array_values(LoyaltyLedgerModel::query()->where('patient_id', $patientId)->orderByDesc('created_at')->orderByDesc('id')->limit($limit)->get()
            ->map(fn (LoyaltyLedgerModel $l) => ['delta' => $l->delta, 'balance_after' => $l->balance_after, 'kind' => $l->kind, 'note' => $l->note, 'created_at' => $l->created_at->format('c')])->all());
    }

    public function earnedFor(string $invoiceId): int
    {
        $earned = (int) LoyaltyLedgerModel::query()->where('invoice_id', $invoiceId)->where('kind', 'earn')->sum('delta');
        $reversed = (int) LoyaltyLedgerModel::query()->where('invoice_id', $invoiceId)->where('kind', 'reverse')->sum('delta');

        return max(0, $earned + $reversed);
    }
}

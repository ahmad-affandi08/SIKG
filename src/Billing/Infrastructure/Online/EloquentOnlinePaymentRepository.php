<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure\Online;

use DateTimeImmutable;
use Src\Billing\Domain\Online\IntentStatus;
use Src\Billing\Domain\Online\OnlinePaymentRepository;
use Src\Billing\Domain\Online\OnlinePaymentSettings;
use Src\Billing\Domain\Online\PaymentIntent;
use Src\Billing\Infrastructure\Eloquent\InvoiceModel;
use Src\Billing\Infrastructure\Eloquent\OnlinePaymentSettingModel;
use Src\Billing\Infrastructure\Eloquent\PaymentIntentModel;
use Src\Shared\Application\PiiProtector;

final readonly class EloquentOnlinePaymentRepository implements OnlinePaymentRepository
{
    public function __construct(private PiiProtector $pii) {}

    public function settings(): OnlinePaymentSettings
    {
        $m = $this->row();

        return new OnlinePaymentSettings(
            $m->enabled, $m->provider, $m->environment, $m->client_id, $m->secret_key_encrypted === null ? null : $this->pii->decrypt($m->secret_key_encrypted),
            $m->payment_method_id, $m->expiry_minutes, $m->webhook_token,
        );
    }

    public function saveSettings(string $actorId, bool $enabled, string $environment, ?string $clientId, ?string $secretKey, ?string $methodId, int $expiryMinutes): void
    {
        $m = $this->row();
        $m->forceFill([
            'enabled' => $enabled, 'environment' => $environment, 'client_id' => $clientId, 'payment_method_id' => $methodId, 'expiry_minutes' => $expiryMinutes, 'updated_by' => $actorId,
        ]);
        if ($secretKey !== null) {
            $m->secret_key_encrypted = $this->pii->encrypt($secretKey);
        }
        $m->save();
    }

    public function clinicForWebhookToken(string $token): ?string
    {
        return OnlinePaymentSettingModel::withoutGlobalScopes()->where('webhook_token', $token)->value('clinic_id');
    }

    public function add(PaymentIntent $intent, string $actorId): void
    {
        $m = new PaymentIntentModel;
        $m->id = $intent->id;
        $m->forceFill([
            'invoice_id' => $intent->invoiceId, 'order_id' => $intent->orderId, 'provider' => OnlinePaymentSettings::PROVIDER_DOKU, 'amount' => $intent->amount, 'status' => $intent->status->value,
            'active_slot' => $intent->status === IntentStatus::Pending ? 1 : null, 'expires_at' => $intent->expiresAt, 'created_by' => $actorId,
        ])->save();
    }

    public function find(string $id): ?PaymentIntent
    {
        $m = PaymentIntentModel::query()->find($id);

        return $m === null ? null : $this->record($m);
    }

    public function findByOrderId(string $orderId): ?PaymentIntent
    {
        $m = PaymentIntentModel::query()->where('order_id', $orderId)->first();

        return $m === null ? null : $this->record($m);
    }

    public function lockForUpdate(string $id): ?PaymentIntent
    {
        $m = PaymentIntentModel::query()->lockForUpdate()->find($id);

        return $m === null ? null : $this->record($m);
    }

    public function activeForInvoice(string $invoiceId): ?PaymentIntent
    {
        $m = PaymentIntentModel::query()->where('invoice_id', $invoiceId)->where('status', IntentStatus::Pending->value)->first();

        return $m === null ? null : $this->record($m);
    }

    public function attachCharge(string $id, ?string $paymentUrl, ?string $gatewayRef, DateTimeImmutable $expiresAt): void
    {
        PaymentIntentModel::query()->findOrFail($id)->forceFill(['payment_url' => $paymentUrl, 'gateway_ref' => $gatewayRef, 'expires_at' => $expiresAt])->save();
    }

    public function transition(string $id, IntentStatus $status, ?string $note, ?DateTimeImmutable $paidAt, ?string $paymentId): void
    {
        PaymentIntentModel::query()->findOrFail($id)->forceFill([
            'status' => $status->value, 'active_slot' => $status === IntentStatus::Pending ? 1 : null, 'note' => $note === null ? null : mb_substr($note, 0, 255), 'paid_at' => $paidAt, 'payment_id' => $paymentId,
        ])->save();
    }

    public function touchChecked(string $id, DateTimeImmutable $at): void
    {
        PaymentIntentModel::query()->whereKey($id)->update(['last_checked_at' => $at]);
    }

    public function pending(int $limit): array
    {
        return array_values(PaymentIntentModel::query()->where('status', IntentStatus::Pending->value)->orderBy('created_at')->limit($limit)->get()
            ->map(fn (PaymentIntentModel $m) => $this->record($m))->all());
    }

    public function listForDesk(int $limit): array
    {
        $rows = PaymentIntentModel::query()->orderByRaw("case when status in ('pending','mismatch') then 0 else 1 end")->orderByDesc('created_at')->limit($limit)->get();
        $numbers = InvoiceModel::query()->whereIn('id', $rows->pluck('invoice_id')->all())->pluck('number', 'id');

        return array_values($rows->map(fn (PaymentIntentModel $m) => [
            'id' => $m->id, 'invoice_id' => $m->invoice_id, 'invoice_number' => $numbers[$m->invoice_id] ?? '-', 'order_id' => $m->order_id, 'amount' => $m->amount, 'status' => $m->status,
            'status_label' => IntentStatus::from($m->status)->label(), 'gateway_ref' => $m->gateway_ref, 'note' => $m->note, 'expires_at' => $m->expires_at->toImmutable(),
            'paid_at' => $m->paid_at?->toImmutable(), 'created_at' => ($m->created_at ?? $m->expires_at)->toImmutable(),
        ])->all());
    }

    public function forInvoice(string $invoiceId): array
    {
        return array_values(PaymentIntentModel::query()->where('invoice_id', $invoiceId)->orderByDesc('created_at')->orderByDesc('id')->get()->map(fn (PaymentIntentModel $m) => $this->record($m))->all());
    }

    private function row(): OnlinePaymentSettingModel
    {
        $m = OnlinePaymentSettingModel::query()->first();
        if ($m === null) {
            $m = new OnlinePaymentSettingModel;
            $m->forceFill(['webhook_token' => bin2hex(random_bytes(20)), 'environment' => OnlinePaymentSettings::ENV_SANDBOX, 'provider' => OnlinePaymentSettings::PROVIDER_DOKU, 'expiry_minutes' => 15, 'enabled' => false]);
            $m->save();
        }

        return $m;
    }

    private function record(PaymentIntentModel $m): PaymentIntent
    {
        return new PaymentIntent(
            $m->id, $m->invoice_id, $m->order_id, $m->amount, IntentStatus::from($m->status), $m->payment_url, $m->gateway_ref, $m->expires_at->toImmutable(),
            $m->paid_at?->toImmutable(), $m->payment_id, $m->note, $m->created_by,
        );
    }
}

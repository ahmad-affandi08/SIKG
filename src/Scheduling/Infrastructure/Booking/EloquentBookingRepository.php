<?php

declare(strict_types=1);

namespace Src\Scheduling\Infrastructure\Booking;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Src\Scheduling\Domain\Booking\BookingRecord;
use Src\Scheduling\Domain\Booking\BookingRepository;
use Src\Scheduling\Domain\Booking\BookingSettings;
use Src\Scheduling\Domain\Booking\BookingStatus;
use Src\Scheduling\Infrastructure\Eloquent\BookingRequestModel;
use Src\Scheduling\Infrastructure\Eloquent\BookingSettingModel;
use Src\Shared\Application\Clock;
use Src\Shared\Application\PiiProtector;
use Src\Shared\Application\TenantContext;

final readonly class EloquentBookingRepository implements BookingRepository
{
    private const HOLDING = [BookingStatus::AwaitingCode->value, BookingStatus::Pending->value];

    public function __construct(private PiiProtector $pii, private Clock $clock, private TenantContext $tenant) {}

    public function settings(): BookingSettings
    {
        $m = BookingSettingModel::query()->first();

        return $m === null ? BookingSettings::defaults() : $this->settingsOf($m);
    }

    public function saveSettings(string $actorId, BookingSettings $s): void
    {
        $m = BookingSettingModel::query()->first() ?? new BookingSettingModel;
        $m->forceFill([
            'enabled' => $s->enabled, 'slug' => $s->slug === '' ? null : $s->slug, 'slot_minutes' => $s->slotMinutes, 'horizon_days' => $s->horizonDays,
            'min_lead_hours' => $s->minLeadHours, 'hold_hours' => $s->holdHours, 'max_active_per_phone' => $s->maxActivePerPhone, 'max_requests_per_hour' => $s->maxRequestsPerHour,
            'otp_template' => $s->otpTemplate, 'welcome_text' => $s->welcomeText, 'updated_by' => $actorId,
        ])->save();
    }

    public function slugTaken(string $slug, bool $exceptOwnClinic): bool
    {
        $query = BookingSettingModel::withoutGlobalScopes()->withTrashed()->where('slug', $slug);
        if ($exceptOwnClinic) {
            $query->where('clinic_id', '!=', $this->tenant->clinicId());
        }

        return $query->exists();
    }

    public function resolveSlug(string $slug): ?array
    {
        $m = BookingSettingModel::withoutGlobalScopes()->where('slug', $slug)->where('enabled', true)->first();

        return $m === null ? null : ['clinic_id' => $m->clinic_id, 'settings' => $this->settingsOf($m)];
    }

    public function add(string $id, string $tokenHash, string $doctorId, DateTimeImmutable $startsAt, DateTimeImmutable $endsAt, string $patientName, string $phone, string $phoneHash, ?string $complaint, BookingStatus $status, DateTimeImmutable $expiresAt, ?string $codeHash, ?DateTimeImmutable $codeExpiresAt, ?string $ipHash): void
    {
        $m = new BookingRequestModel;
        $m->id = $id;
        $m->forceFill([
            'token_hash' => $tokenHash, 'doctor_id' => $doctorId, 'starts_at' => $startsAt, 'ends_at' => $endsAt, 'patient_name' => $patientName,
            'phone_encrypted' => $this->pii->encrypt($phone), 'phone_hash' => $phoneHash, 'complaint' => $complaint, 'status' => $status->value,
            'expires_at' => $expiresAt, 'code_hash' => $codeHash, 'code_expires_at' => $codeExpiresAt, 'code_sent_count' => $codeHash === null ? 0 : 1, 'ip_hash' => $ipHash,
        ])->save();
    }

    public function findByTokenHash(string $tokenHash): ?BookingRecord
    {
        $m = BookingRequestModel::query()->where('token_hash', $tokenHash)->first();

        return $m === null ? null : $this->record($m);
    }

    public function find(string $id): ?BookingRecord
    {
        $m = BookingRequestModel::query()->find($id);

        return $m === null ? null : $this->record($m);
    }

    public function lockForUpdate(string $id): ?BookingRecord
    {
        $m = BookingRequestModel::query()->lockForUpdate()->find($id);

        return $m === null ? null : $this->record($m);
    }

    public function activeCountForPhone(string $phoneHash, DateTimeImmutable $now): int
    {
        $holding = BookingRequestModel::query()->where('phone_hash', $phoneHash)->whereIn('status', self::HOLDING)->where('expires_at', '>', $now)->count();
        $upcoming = BookingRequestModel::query()->where('phone_hash', $phoneHash)->where('status', BookingStatus::Confirmed->value)->where('starts_at', '>=', $now)
            ->whereIn('appointment_id', DB::table('appointments')->whereNotIn('status', ['cancelled', 'no_show'])->whereNull('deleted_at')->select('id'))->count();

        return $holding + $upcoming;
    }

    public function requestsSince(DateTimeImmutable $since): int
    {
        return BookingRequestModel::query()->where('created_at', '>=', $since)->count();
    }

    public function codesSentSince(string $phoneHash, DateTimeImmutable $since): int
    {
        return (int) BookingRequestModel::query()->where('phone_hash', $phoneHash)->where('created_at', '>=', $since)->sum('code_sent_count');
    }

    public function holdsBetween(string $doctorId, DateTimeImmutable $from, DateTimeImmutable $to, DateTimeImmutable $now): int
    {
        return BookingRequestModel::query()->where('doctor_id', $doctorId)->whereIn('status', self::HOLDING)->where('expires_at', '>', $now)
            ->where('starts_at', '<', $to)->where('ends_at', '>', $from)->count();
    }

    public function setCode(string $id, string $codeHash, DateTimeImmutable $expiresAt): void
    {
        $m = BookingRequestModel::query()->findOrFail($id);
        // Selama menunggu kode, slot hanya ditahan sampai kode berlaku.
        $m->forceFill(['code_hash' => $codeHash, 'code_expires_at' => $expiresAt, 'expires_at' => $expiresAt, 'code_attempts' => 0, 'code_sent_count' => $m->code_sent_count + 1])->save();
    }

    public function bumpAttempts(string $id): void
    {
        $m = BookingRequestModel::query()->findOrFail($id);
        $m->forceFill(['code_attempts' => $m->code_attempts + 1])->save();
    }

    public function markVerified(string $id, DateTimeImmutable $at, DateTimeImmutable $holdUntil): void
    {
        BookingRequestModel::query()->findOrFail($id)->forceFill(['verified' => true, 'verified_at' => $at, 'expires_at' => $holdUntil, 'status' => BookingStatus::Pending->value, 'code_hash' => null, 'code_expires_at' => null])->save();
    }

    public function transition(string $id, BookingStatus $status, ?string $actorId, ?string $note, ?string $patientId, ?string $appointmentId, DateTimeImmutable $at): void
    {
        BookingRequestModel::query()->findOrFail($id)->forceFill([
            'status' => $status->value, 'decided_by' => $actorId, 'decision_note' => $note, 'patient_id' => $patientId, 'appointment_id' => $appointmentId, 'decided_at' => $at,
        ])->save();
    }

    public function listForDesk(int $limit): array
    {
        $now = $this->clock->now();
        $rows = BookingRequestModel::query()
            ->orderByRaw("case when status = 'pending' then 0 when status = 'awaiting_code' then 1 else 2 end")
            ->orderBy('starts_at')->limit($limit)->get();

        return array_values($rows->map(fn (BookingRequestModel $m) => [
            'id' => $m->id, 'status' => $m->status, 'status_label' => BookingStatus::from($m->status)->label(), 'patient_name' => $m->patient_name, 'doctor_id' => $m->doctor_id,
            'starts_at' => $m->starts_at->toImmutable(), 'ends_at' => $m->ends_at->toImmutable(), 'complaint' => $m->complaint, 'verified' => $m->verified,
            'patient_id' => $m->patient_id, 'appointment_id' => $m->appointment_id, 'decision_note' => $m->decision_note,
            'created_at' => $m->created_at?->toImmutable() ?? $now, 'phone_masked' => $this->masked($this->pii->decrypt($m->phone_encrypted)),
        ])->all());
    }

    public function phoneOf(string $id): ?string
    {
        $m = BookingRequestModel::query()->find($id);

        return $m === null ? null : $this->pii->decrypt($m->phone_encrypted);
    }

    public function phoneHashOf(string $id): ?string
    {
        return BookingRequestModel::query()->whereKey($id)->value('phone_hash');
    }

    public function expireDue(DateTimeImmutable $now): int
    {
        return BookingRequestModel::query()->whereIn('status', self::HOLDING)->where('expires_at', '<=', $now)->update(['status' => BookingStatus::Expired->value, 'decided_at' => $now, 'decision_note' => 'Melewati masa tahan.']);
    }

    private function settingsOf(BookingSettingModel $m): BookingSettings
    {
        return new BookingSettings($m->enabled, (string) $m->slug, $m->slot_minutes, $m->horizon_days, $m->min_lead_hours, $m->hold_hours, $m->max_active_per_phone, $m->otp_template, $m->welcome_text, $m->max_requests_per_hour);
    }

    private function record(BookingRequestModel $m): BookingRecord
    {
        return new BookingRecord(
            $m->id, $m->doctor_id, $m->starts_at->toImmutable(), $m->ends_at->toImmutable(), $m->patient_name, $m->complaint, BookingStatus::from($m->status), $m->verified,
            $m->patient_id, $m->appointment_id, $m->expires_at->toImmutable(), $m->decision_note, $m->code_hash, $m->code_expires_at?->toImmutable(), $m->code_attempts,
            $m->code_sent_count, ($m->created_at ?? $m->starts_at)->toImmutable(),
        );
    }

    private function masked(string $phone): string
    {
        return str_repeat('*', max(strlen($phone) - 4, 0)).substr($phone, -4);
    }
}

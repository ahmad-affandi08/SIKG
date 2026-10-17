<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Notifications;

use Illuminate\Database\QueryException;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Notifications\Notification;
use Src\Shared\Application\Notifications\Notifier;
use Src\Shared\Application\TenantContext;

final readonly class DatabaseNotifier implements Notifier
{
    public function __construct(private TenantContext $tenant, private Clock $clock) {}

    public function publish(Notification $notification): bool
    {
        $clinicId = $this->tenant->clinicId();
        if ($clinicId === null) {
            return false;
        }
        if (NotificationModel::query()->where('dedupe_key', $notification->dedupeKey)->exists()) {
            return false;
        }

        try {
            (new NotificationModel)->forceFill([
                'clinic_id' => $clinicId,
                'branch_id' => $this->tenant->branchId(),
                'kind' => $notification->kind,
                'title' => mb_substr($notification->title, 0, Notification::MAX_TITLE),
                'body' => mb_substr($notification->body, 0, Notification::MAX_BODY),
                'link' => $notification->link,
                'permission' => $notification->permission,
                'dedupe_key' => mb_substr($notification->dedupeKey, 0, 120),
                'created_at' => $this->clock->now(),
            ])->save();
        } catch (QueryException $e) {
            // Dua proses menerbitkan kunci yang sama bersamaan: kunci unik menahan salah satunya.
            if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'UNIQUE')) {
                return false;
            }

            throw $e;
        }

        return true;
    }
}

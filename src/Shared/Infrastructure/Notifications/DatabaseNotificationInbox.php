<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Notifications;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Notifications\NotificationInbox;

final readonly class DatabaseNotificationInbox implements NotificationInbox
{
    public function __construct(private Clock $clock) {}

    public function list(string $userId, array $permissions, int $limit): array
    {
        $rows = $this->visible($permissions)->orderByDesc('created_at')->orderByDesc('id')->limit(max(1, min($limit, 100)))->get();
        $read = DB::table('notification_reads')->where('user_id', $userId)->whereIn('notification_id', $rows->pluck('id')->all())->pluck('notification_id')->all();

        $items = [];
        foreach ($rows as $n) {
            $items[] = [
                'id' => $n->id,
                'kind' => $n->kind,
                'title' => $n->title,
                'body' => $n->body,
                'link' => $n->link,
                'created_at' => $n->created_at->toIso8601String(),
                'read' => in_array($n->id, $read, true),
            ];
        }

        return $items;
    }

    public function unreadCount(string $userId, array $permissions): int
    {
        return $this->visible($permissions)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('notification_reads')->whereColumn('notification_reads.notification_id', 'notifications.id')->where('notification_reads.user_id', $userId))
            ->count();
    }

    public function markRead(string $userId, array $permissions, string $notificationId): void
    {
        if ($this->visible($permissions)->whereKey($notificationId)->exists()) {
            DB::table('notification_reads')->insertOrIgnore(['notification_id' => $notificationId, 'user_id' => $userId, 'read_at' => $this->clock->now()]);
        }
    }

    public function markAllRead(string $userId, array $permissions): void
    {
        $ids = $this->visible($permissions)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('notification_reads')->whereColumn('notification_reads.notification_id', 'notifications.id')->where('notification_reads.user_id', $userId))
            ->pluck('id');
        $now = $this->clock->now();
        foreach ($ids->chunk(200) as $chunk) {
            DB::table('notification_reads')->insertOrIgnore($chunk->map(fn (string $id) => ['notification_id' => $id, 'user_id' => $userId, 'read_at' => $now])->all());
        }
    }

    /**
     * @param  list<string>  $permissions
     * @return Builder<NotificationModel>
     */
    private function visible(array $permissions): Builder
    {
        return NotificationModel::query()->whereIn('permission', $permissions === [] ? [''] : $permissions);
    }
}

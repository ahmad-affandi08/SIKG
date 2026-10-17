<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Application\Notifications;

use Src\IdentityAccess\Domain\UserPermissions;
use Src\Shared\Application\Notifications\NotificationInbox;

/**
 * SYS-05: kotak masuk pemberitahuan milik pengguna yang sedang masuk. Pemberitahuan yang terlihat ditentukan izin pengguna
 * saat ini (diambil di sini), bukan peran, sehingga penyesuaian izin lewat AUTH-07 langsung berlaku.
 */
final readonly class UserNotifications
{
    public const LIMIT = 30;

    public function __construct(private NotificationInbox $inbox, private UserPermissions $permissions) {}

    /**
     * @return array{unread: int, items: list<array{id: string, kind: string, title: string, body: string, link: string|null, created_at: string, read: bool}>}
     */
    public function overview(string $userId, int $limit = self::LIMIT): array
    {
        $permissions = $this->permissions($userId);

        return [
            'unread' => $this->inbox->unreadCount($userId, $permissions),
            'items' => $this->inbox->list($userId, $permissions, $limit),
        ];
    }

    public function unread(string $userId): int
    {
        return $this->inbox->unreadCount($userId, $this->permissions($userId));
    }

    public function markRead(string $userId, string $notificationId): void
    {
        $this->inbox->markRead($userId, $this->permissions($userId), $notificationId);
    }

    public function markAllRead(string $userId): void
    {
        $this->inbox->markAllRead($userId, $this->permissions($userId));
    }

    /**
     * @return list<string>
     */
    private function permissions(string $userId): array
    {
        return $this->permissions->of($userId);
    }
}

<?php

declare(strict_types=1);

namespace Src\Shared\Application\Notifications;

/**
 * Kotak masuk pemberitahuan milik satu pengguna. Pengguna melihat pemberitahuan yang izinnya ia miliki; status dibaca
 * disimpan per pengguna. Izin diberikan pemanggil (Presentation) sehingga port ini tidak mengenal konteks IdentityAccess.
 */
interface NotificationInbox
{
    /**
     * @param  list<string>  $permissions  izin pengguna saat ini
     * @return list<array{id: string, kind: string, title: string, body: string, link: string|null, created_at: string, read: bool}>
     */
    public function list(string $userId, array $permissions, int $limit): array;

    /**
     * @param  list<string>  $permissions
     */
    public function unreadCount(string $userId, array $permissions): int;

    /**
     * @param  list<string>  $permissions
     */
    public function markRead(string $userId, array $permissions, string $notificationId): void;

    /**
     * @param  list<string>  $permissions
     */
    public function markAllRead(string $userId, array $permissions): void;
}

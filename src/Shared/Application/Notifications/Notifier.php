<?php

declare(strict_types=1);

namespace Src\Shared\Application\Notifications;

interface Notifier
{
    /**
     * Menerbitkan pemberitahuan dalam aplikasi.
     *
     * @return bool false bila `dedupeKey` yang sama sudah pernah terbit (tidak ditulis ulang)
     */
    public function publish(Notification $notification): bool;
}

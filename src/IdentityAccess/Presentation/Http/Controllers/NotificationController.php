<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\IdentityAccess\Application\Notifications\UserNotifications;

/**
 * SYS-05: halaman dan endpoint polling pemberitahuan dalam aplikasi. Setiap pengguna aktif hanya melihat pemberitahuan
 * yang izinnya ia miliki (UserNotifications); endpoint polling ringan dan tanpa data pasien.
 */
final class NotificationController
{
    public function __construct(private readonly UserNotifications $notifications) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Notifications/Index', $this->notifications->overview($this->userId($request), 100));
    }

    public function poll(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->notifications->overview($this->userId($request))]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $this->notifications->markRead($this->userId($request), $notification);

        return response()->json(['data' => ['unread' => $this->notifications->unread($this->userId($request))]]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $this->notifications->markAllRead($this->userId($request));

        return response()->json(['data' => ['unread' => 0]]);
    }

    private function userId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}

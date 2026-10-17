<?php

declare(strict_types=1);

namespace Src\Reporting\Presentation\Http;

use Illuminate\Http\Request;
use Src\Reporting\Application\ReportViewer;

/**
 * Membangun ReportViewer dari pengguna yang masuk dengan memeriksa izin yang relevan bagi laporan (M9).
 */
final class ReportViewers
{
    public static function from(Request $request): ReportViewer
    {
        $user = $request->user();
        $id = $user?->getAuthIdentifier();
        abort_unless($user !== null && is_string($id), 403);

        $granted = [];
        foreach (ReportViewer::relevantPermissions() as $permission) {
            if ($user->can($permission)) {
                $granted[] = $permission;
            }
        }

        return new ReportViewer($id, $granted);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Shared\Application\HealthMonitor;

/**
 * SYS-07: GET /health dengan header X-Health-Token. Tanpa PHI. Token kosong atau salah ditolak (403).
 * Untuk pemantau eksternal; /up tetap publik tanpa detail.
 */
final class HealthController
{
    public function __invoke(Request $request, HealthMonitor $monitor): JsonResponse
    {
        $expected = config('sikg.health_token');
        $given = $request->header('X-Health-Token');

        if (! is_string($expected) || $expected === '' || ! is_string($given) || ! hash_equals($expected, $given)) {
            return response()->json(['message' => 'Tidak diizinkan.'], 403);
        }

        $snapshot = $monitor->snapshot();

        return response()->json($snapshot->toArray(), $snapshot->status === 'ok' ? 200 : 503);
    }
}

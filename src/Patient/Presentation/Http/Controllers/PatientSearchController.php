<?php

declare(strict_types=1);

namespace Src\Patient\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Patient\Application\CheckDuplicates;
use Src\Patient\Application\SearchPatients;
use Src\Patient\Presentation\Http\Requests\PatientRequest;

/**
 * Endpoint JSON pasien. Memakai POST (bukan GET dengan ?q=) karena kata kunci bisa berupa nama, NIK, atau nomor HP,
 * dan PHI tidak boleh berada di URL atau query string (M11). Penyimpangan dari tabel route PRD dicatat di Q-12.
 */
final class PatientSearchController
{
    public function search(Request $request, SearchPatients $search): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:1', 'max:100']]);

        $results = $search($request->string('q')->toString());

        return response()->json([
            'data' => array_map(fn ($p) => $p->toArray(), $results),
            'meta' => ['count' => count($results)],
        ]);
    }

    public function duplicates(PatientRequest $request, CheckDuplicates $check): JsonResponse
    {
        $ignore = $request->input('ignore_id');
        $candidates = $check($request->toDraft(), is_string($ignore) && $ignore !== '' ? $ignore : null);

        return response()->json([
            'data' => array_map(fn ($c) => $c->toArray(), $candidates),
            'meta' => ['count' => count($candidates)],
        ]);
    }
}

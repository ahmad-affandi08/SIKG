<?php

declare(strict_types=1);

namespace Src\Clinical\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Clinical\Application\OdontogramService;

/**
 * Endpoint JSON odontogram (docs PRD bagian route): odontogram terkini dan simpan kondisi per gigi.
 */
final class OdontogramController
{
    public function show(Request $request, string $patient, OdontogramService $odontogram): JsonResponse
    {
        $data = $request->validate(['encounter' => ['nullable', 'string', 'size:26']]);

        return response()->json(['data' => $odontogram->current($patient, 'odontogram.show', $data['encounter'] ?? null), 'meta' => (object) []]);
    }

    public function update(Request $request, string $encounter, int $fdi, OdontogramService $odontogram): JsonResponse
    {
        $data = $request->validate([
            'surface' => ['required', 'string', 'size:1'],
            'condition_code' => ['required', 'string', 'max:10'],
        ], ['surface.required' => 'Permukaan wajib dipilih.', 'condition_code.required' => 'Kondisi wajib dipilih.']);

        $actor = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($actor), 403);

        $odontogram->setCondition($actor, $encounter, $fdi, $data['surface'], $data['condition_code']);

        return response()->json(['data' => ['tooth_fdi' => $fdi, 'surface' => $data['surface'], 'condition_code' => $data['condition_code']], 'meta' => (object) []]);
    }
}

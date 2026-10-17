<?php

declare(strict_types=1);

namespace Src\Clinical\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Clinical\Application\Diagnosis\DiagnosisCatalog;

/**
 * GET /internal-api/diagnoses/search?q= : autocomplete ICD-10, maksimal 20 hasil, hanya kode aktif.
 */
final class DiagnosisSearchController
{
    public function __invoke(Request $request, DiagnosisCatalog $catalog): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:1', 'max:100']]);

        $results = $catalog->search($request->string('q')->toString());

        return response()->json([
            'data' => array_map(fn ($d) => ['id' => $d->id, 'code' => $d->code, 'name' => $d->name], $results),
            'meta' => ['count' => count($results)],
        ]);
    }
}

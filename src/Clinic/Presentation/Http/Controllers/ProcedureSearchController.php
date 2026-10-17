<?php

declare(strict_types=1);

namespace Src\Clinic\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Clinic\Contracts\ProcedureCatalog;

/**
 * GET /internal-api/procedures/search?q= : autocomplete tindakan aktif dengan tarif saat ini, maksimal 20 hasil.
 */
final class ProcedureSearchController
{
    public function __invoke(Request $request, ProcedureCatalog $catalog): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:1', 'max:100']]);

        $results = $catalog->search($request->string('q')->toString());

        return response()->json([
            'data' => array_map(fn ($p) => $p->toArray(), $results),
            'meta' => ['count' => count($results)],
        ]);
    }
}

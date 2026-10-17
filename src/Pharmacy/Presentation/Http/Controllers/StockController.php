<?php

declare(strict_types=1);

namespace Src\Pharmacy\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Pharmacy\Application\StockService;
use Src\Pharmacy\Contracts\MedicineCatalog;

/**
 * OBT-05: stok masuk, stok keluar, dan penyesuaian. Membaca: stock.view; mengubah: stock.manage.
 */
final class StockController
{
    public function index(Request $request, StockService $stock): Response
    {
        $data = $request->validate(['medicine' => ['nullable', 'string', 'size:26']]);
        $medicine = $data['medicine'] ?? null;

        return Inertia::render('Stock/Index', [
            ...$stock->overview(),
            'selected' => $medicine,
            'movements' => $medicine === null ? [] : array_map(fn ($m) => [
                'id' => $m->id,
                'type' => $m->type->value,
                'type_label' => $m->type->label(),
                'quantity' => $m->quantity,
                'balance_after' => $m->balanceAfter,
                'reason' => $m->reason,
                'batch_no' => $m->batchNo,
                'expiry_date' => $m->expiryDate,
                'created_at' => $m->createdAt->format('c'),
            ], $stock->history($medicine)),
            'can' => [
                'manage' => (bool) $request->user()?->can('stock.manage'),
                'override' => (bool) $request->user()?->can('stock.override'),
            ],
        ]);
    }

    public function movement(Request $request, StockService $stock): RedirectResponse
    {
        $data = $request->validate([
            'medicine_id' => ['required', 'string', 'size:26'],
            'type' => ['required', 'in:in,out'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'batch_no' => ['nullable', 'string', 'max:50'],
            'expiry_date' => ['nullable', 'date_format:Y-m-d'],
            'reason' => ['nullable', 'string', 'max:255'],
            'override_reason' => ['nullable', 'string', 'max:255'],
        ], ['medicine_id.required' => 'Obat wajib dipilih.', 'quantity.required' => 'Jumlah wajib diisi.']);

        $actor = $this->actorId($request);
        if ($data['type'] === 'in') {
            $stock->receive($actor, $data['medicine_id'], (int) $data['quantity'], $data['batch_no'] ?? null, $data['expiry_date'] ?? null, $data['reason'] ?? null);
        } else {
            $override = $request->user()?->can('stock.override') === true ? ($data['override_reason'] ?? null) : null;
            $stock->dispose($actor, $data['medicine_id'], (int) $data['quantity'], (string) ($data['reason'] ?? ''), $override);
        }

        return back()->with('success', 'Mutasi stok disimpan.');
    }

    public function adjustment(Request $request, StockService $stock): RedirectResponse
    {
        $data = $request->validate([
            'medicine_id' => ['required', 'string', 'size:26'],
            'counted_quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'reason' => ['required', 'string', 'max:255'],
        ], ['reason.required' => 'Alasan penyesuaian wajib diisi.', 'counted_quantity.required' => 'Jumlah fisik wajib diisi.']);

        $stock->adjust($this->actorId($request), $data['medicine_id'], (int) $data['counted_quantity'], $data['reason']);

        return back()->with('success', 'Penyesuaian stok disimpan.');
    }

    public function search(Request $request, MedicineCatalog $catalog): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:1', 'max:100']]);
        $results = $catalog->search($request->string('q')->toString());

        return response()->json(['data' => $results, 'meta' => ['count' => count($results)]]);
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}

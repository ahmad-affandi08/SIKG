<?php

declare(strict_types=1);

namespace Src\Pharmacy\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Pharmacy\Application\StockTransferService;

/**
 * P3-04: transfer stok antar cabang. Melihat: stock.view; memindahkan: stock.manage (dipasang di route).
 */
final class StockTransferController
{
    public function __construct(private readonly StockTransferService $transfers) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Stock/Transfers', [...$this->transfers->overview(), 'can' => ['manage' => (bool) $request->user()?->can('stock.manage')]]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'medicine_id' => ['required', 'string', 'size:26'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'from_branch_id' => ['nullable', 'string', 'size:26'],
            'to_branch_id' => ['nullable', 'string', 'size:26'],
            'reason' => ['nullable', 'string', 'max:255'],
        ], [
            'medicine_id.required' => 'Pilih obat atau bahan.', 'quantity.required' => 'Jumlah wajib diisi.', 'quantity.integer' => 'Jumlah harus bilangan bulat.', 'quantity.min' => 'Jumlah harus lebih dari nol.',
        ]);
        $actor = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($actor), 403);

        $result = $this->transfers->transfer(
            $actor, $data['medicine_id'], (int) $data['quantity'], $this->branch($data['from_branch_id'] ?? null), $this->branch($data['to_branch_id'] ?? null), $data['reason'] ?? null,
        );

        return back()->with('success', 'Stok dipindahkan. Saldo asal '.$result['from_after'].', saldo tujuan '.$result['to_after'].'.');
    }

    private function branch(?string $id): ?string
    {
        return $id === null || $id === '' ? null : $id;
    }
}

<?php

declare(strict_types=1);

namespace Src\Pharmacy\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Pharmacy\Application\PurchaseOrderService;
use Src\Pharmacy\Application\PurchaseQueries;
use Src\Shared\Application\RequiredFields;

/**
 * P2-05: pembelian ke pemasok dan daftar batch. Membaca: stock.view; mengubah: stock.manage (dipasang di route).
 */
final class PurchaseOrderController
{
    public function __construct(private readonly PurchaseOrderService $orders, private readonly PurchaseQueries $queries) {}

    public function index(Request $request): Response
    {
        $query = $request->validate(['status' => ['nullable', 'string', 'max:12']]);

        return Inertia::render('Stock/Purchasing/Index', [
            ...$this->queries->list($query['status'] ?? null),
            'filters' => ['status' => $query['status'] ?? ''],
            'can' => ['manage' => (bool) $request->user()?->can('stock.manage')],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Stock/Purchasing/Create', [...$this->queries->createForm(), 'required' => RequiredFields::from(self::createRules())]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(self::createRules(), [
            'supplier_id.required' => 'Pemasok wajib dipilih.',
            'lines.required' => 'Tambahkan minimal satu obat.',
            'lines.min' => 'Tambahkan minimal satu obat.',
            'lines.*.medicine_id.required' => 'Obat wajib dipilih.',
            'lines.*.quantity.required' => 'Jumlah wajib diisi.',
            'lines.*.quantity.min' => 'Jumlah minimal 1.',
            'lines.*.unit_cost_amount.required' => 'Harga beli wajib diisi.',
        ]);

        $lines = array_values(array_map(fn (array $l) => ['medicine_id' => (string) $l['medicine_id'], 'quantity' => (int) $l['quantity'], 'unit_cost_amount' => (int) $l['unit_cost_amount']], $data['lines']));
        $id = $this->orders->create($this->actorId($request), $data['supplier_id'], $data['expected_date'] ?? null, $data['note'] ?? null, $lines);

        return to_route('stock.purchase-orders.show', ['order' => $id])->with('success', 'Pesanan pembelian dibuat sebagai draft.');
    }

    public function show(Request $request, string $order): Response
    {
        return Inertia::render('Stock/Purchasing/Show', [
            'order' => $this->queries->detail($order),
            'can' => ['manage' => (bool) $request->user()?->can('stock.manage')],
        ]);
    }

    public function order(Request $request, string $order): RedirectResponse
    {
        $this->orders->order($this->actorId($request), $order);

        return back()->with('success', 'Pesanan ditandai sudah dipesan ke pemasok.');
    }

    public function cancel(Request $request, string $order): RedirectResponse
    {
        $this->orders->cancel($this->actorId($request), $order);

        return back()->with('success', 'Pesanan dibatalkan.');
    }

    public function receive(Request $request, string $order): RedirectResponse
    {
        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.item_id' => ['required', 'string', 'size:26'],
            'lines.*.quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'lines.*.batch_no' => ['nullable', 'string', 'max:50'],
            'lines.*.expiry_date' => ['nullable', 'date_format:Y-m-d'],
        ], ['lines.required' => 'Isi jumlah diterima pada minimal satu baris.', 'lines.*.expiry_date.date_format' => 'Tanggal kedaluwarsa tidak valid.']);

        $lines = array_values(array_map(fn (array $l) => [
            'item_id' => (string) $l['item_id'],
            'quantity' => (int) $l['quantity'],
            'batch_no' => isset($l['batch_no']) && $l['batch_no'] !== '' ? (string) $l['batch_no'] : null,
            'expiry_date' => isset($l['expiry_date']) && $l['expiry_date'] !== '' ? (string) $l['expiry_date'] : null,
        ], $data['lines']));
        $this->orders->receive($this->actorId($request), $order, $lines);

        return back()->with('success', 'Penerimaan barang dicatat dan stok bertambah.');
    }

    public function batches(): Response
    {
        return Inertia::render('Stock/Batches', ['batches' => $this->queries->batches()]);
    }

    /**
     * @return array<string, list<string>>
     */
    private static function createRules(): array
    {
        return [
            'supplier_id' => ['required', 'string', 'size:26'],
            'expected_date' => ['nullable', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.medicine_id' => ['required', 'string', 'size:26'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'lines.*.unit_cost_amount' => ['required', 'integer', 'min:0', 'max:9999999999'],
        ];
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}

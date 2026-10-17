<?php

declare(strict_types=1);

namespace Src\Insurance\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Insurance\Application\ClaimQueries;
use Src\Insurance\Application\ClaimService;
use Src\Insurance\Presentation\Http\Requests\ClaimRequest;

/**
 * P2-02: klaim ke penjamin dan piutang penjamin. Izin dipasang per route: invoice.view untuk melihat, invoice.edit untuk membuat
 * dan memutuskan, payment.create untuk mencatat uang dari penjamin, report.financial untuk piutang.
 */
final class ClaimController
{
    public function __construct(private readonly ClaimService $claims, private readonly ClaimQueries $queries) {}

    public function index(Request $request): Response
    {
        $query = $request->validate(['status' => ['nullable', 'string', 'max:12'], 'insurer' => ['nullable', 'string', 'size:26']]);

        return Inertia::render('Insurance/Claims/Index', [
            ...$this->queries->list($query['status'] ?? null, $query['insurer'] ?? null),
            'filters' => ['status' => $query['status'] ?? '', 'insurer' => $query['insurer'] ?? ''],
        ]);
    }

    public function create(Request $request): Response
    {
        $query = $request->validate(['invoice' => ['required', 'string', 'size:26']]);

        return Inertia::render('Insurance/Claims/Create', $this->queries->createForm($query['invoice']));
    }

    public function store(ClaimRequest $request): RedirectResponse
    {
        $policy = $request->input('policy_id');
        $notes = $request->input('notes');
        $id = $this->claims->create(
            $this->actorId($request),
            $request->string('invoice_id')->toString(),
            $request->string('insurer_id')->toString(),
            is_string($policy) && $policy !== '' ? $policy : null,
            (int) $request->input('claimed_amount'),
            is_string($notes) ? $notes : null,
        );

        return to_route('insurance.claims.show', ['claim' => $id])->with('success', 'Klaim dibuat sebagai draft.');
    }

    public function show(string $claim): Response
    {
        return Inertia::render('Insurance/Claims/Show', $this->queries->detail($claim, 'insurance.claims.show'));
    }

    public function submit(Request $request, string $claim): RedirectResponse
    {
        $this->claims->submit($this->actorId($request), $claim);

        return back()->with('success', 'Klaim diajukan ke penjamin.');
    }

    public function cancel(Request $request, string $claim): RedirectResponse
    {
        $this->claims->cancel($this->actorId($request), $claim);

        return back()->with('success', 'Klaim draft dibatalkan.');
    }

    public function approve(Request $request, string $claim): RedirectResponse
    {
        $data = $request->validate(
            ['approved_amount' => ['required', 'integer', 'min:1', 'max:999999999999'], 'note' => ['nullable', 'string', 'max:255']],
            ['approved_amount.required' => 'Jumlah disetujui wajib diisi.', 'approved_amount.integer' => 'Jumlah disetujui berupa bilangan bulat rupiah.'],
        );
        $this->claims->approve($this->actorId($request), $claim, (int) $data['approved_amount'], $data['note'] ?? null);

        return back()->with('success', 'Persetujuan penjamin dicatat.');
    }

    public function reject(Request $request, string $claim): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], ['reason.required' => 'Alasan penolakan wajib diisi.']);
        $this->claims->reject($this->actorId($request), $claim, $data['reason']);

        return back()->with('success', 'Penolakan penjamin dicatat.');
    }

    public function receive(Request $request, string $claim): RedirectResponse
    {
        $data = $request->validate(
            ['amount' => ['required', 'integer', 'min:1', 'max:999999999999'], 'payment_method_id' => ['required', 'string', 'size:26'], 'reference_no' => ['nullable', 'string', 'max:100'], 'idempotency_key' => ['required', 'string', 'max:64']],
            ['amount.required' => 'Jumlah pembayaran wajib diisi.', 'amount.integer' => 'Jumlah pembayaran berupa bilangan bulat rupiah.', 'payment_method_id.required' => 'Pilih metode pembayaran.', 'idempotency_key.required' => 'Kunci pembayaran wajib ada.'],
        );
        $this->claims->receive($this->actorId($request), $claim, (int) $data['amount'], $data['payment_method_id'], $data['idempotency_key'], $data['reference_no'] ?? null);

        return back()->with('success', 'Pembayaran dari penjamin dicatat.');
    }

    public function receivables(): Response
    {
        return Inertia::render('Insurance/Receivables', $this->queries->receivables());
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}

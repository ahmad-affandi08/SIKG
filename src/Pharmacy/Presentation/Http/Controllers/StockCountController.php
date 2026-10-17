<?php

declare(strict_types=1);

namespace Src\Pharmacy\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Pharmacy\Application\StockCountService;

/**
 * P2-05: opname terjadwal. Membaca: stock.view; mengubah: stock.manage (dipasang di route).
 */
final class StockCountController
{
    public function __construct(private readonly StockCountService $counts) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Stock/Counts/Index', [...$this->counts->overview(), 'can' => ['manage' => (bool) $request->user()?->can('stock.manage')]]);
    }

    public function show(Request $request, string $session): Response
    {
        return Inertia::render('Stock/Counts/Show', [...$this->counts->detail($session), 'can' => ['manage' => (bool) $request->user()?->can('stock.manage')]]);
    }

    public function start(Request $request): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:255']]);
        $id = $this->counts->start($this->actorId($request), $data['note'] ?? null);

        return to_route('stock.counts.show', ['session' => $id])->with('success', 'Sesi opname dibuka.');
    }

    public function schedule(Request $request): RedirectResponse
    {
        $data = $request->validate(
            ['enabled' => ['required', 'boolean'], 'interval_days' => ['required', 'integer', 'min:7', 'max:365']],
            ['interval_days.required' => 'Interval opname wajib diisi.', 'interval_days.min' => 'Interval minimal 7 hari.', 'interval_days.max' => 'Interval maksimal 365 hari.'],
        );
        $this->counts->saveSchedule($this->actorId($request), (bool) $data['enabled'], (int) $data['interval_days']);

        return back()->with('success', 'Jadwal opname disimpan.');
    }

    public function save(Request $request, string $session): RedirectResponse
    {
        $data = $request->validate([
            'counts' => ['required', 'array', 'max:2000'],
            'counts.*' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);
        $counted = [];
        foreach ($data['counts'] as $lineId => $value) {
            $counted[(string) $lineId] = $value === null || $value === '' ? null : (int) $value;
        }
        $this->counts->record($this->actorId($request), $session, $counted);

        return back()->with('success', 'Hasil hitung disimpan.');
    }

    public function complete(Request $request, string $session): RedirectResponse
    {
        $this->counts->complete($this->actorId($request), $session);

        return to_route('stock.counts')->with('success', 'Opname selesai dan stok disesuaikan.');
    }

    public function cancel(Request $request, string $session): RedirectResponse
    {
        $this->counts->cancel($this->actorId($request), $session);

        return to_route('stock.counts')->with('success', 'Sesi opname dibatalkan.');
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}

<?php

declare(strict_types=1);

namespace Src\Lab\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Src\Lab\Application\LabOrderQueries;
use Src\Lab\Application\LabOrderService;
use Src\Lab\Domain\WorkType;

/**
 * P2-06: pesanan lab gigi. Izin dipasang per route: lab.view untuk melihat, encounter.write untuk memesan dan mengubah status,
 * invoice.edit untuk menagihkan biaya ke pasien.
 */
final class LabOrderController
{
    public function __construct(private readonly LabOrderService $service, private readonly LabOrderQueries $queries) {}

    public function index(Request $request): Response
    {
        $query = $request->validate(['status' => ['nullable', 'string', 'max:10']]);

        return Inertia::render('Lab/Index', [
            ...$this->queries->list($query['status'] ?? null),
            'filters' => ['status' => $query['status'] ?? ''],
        ]);
    }

    public function create(Request $request): Response
    {
        $query = $request->validate(['patient' => ['required', 'string', 'size:26']]);

        return Inertia::render('Lab/Create', $this->queries->createForm($query['patient']));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'string', 'size:26'],
            'doctor_id' => ['required', 'string', 'size:26'],
            'laboratory_id' => ['required', 'string', 'size:26'],
            'work_type' => ['required', Rule::enum(WorkType::class)],
            'tooth_notes' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:500'],
            'shade' => ['nullable', 'string', 'max:30'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'lab_cost_amount' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'patient_charge_amount' => ['required', 'integer', 'min:0', 'max:999999999999'],
        ], [
            'doctor_id.required' => 'Dokter wajib dipilih.',
            'laboratory_id.required' => 'Laboratorium wajib dipilih.',
            'work_type.required' => 'Jenis pekerjaan wajib dipilih.',
            'description.required' => 'Uraian pekerjaan wajib diisi.',
            'lab_cost_amount.required' => 'Biaya lab wajib diisi (isi 0 bila belum diketahui).',
            'lab_cost_amount.integer' => 'Biaya lab berupa bilangan bulat rupiah.',
            'patient_charge_amount.required' => 'Harga ke pasien wajib diisi (isi 0 bila tidak ditagihkan terpisah).',
            'patient_charge_amount.integer' => 'Harga ke pasien berupa bilangan bulat rupiah.',
        ]);

        $id = $this->service->place(
            $this->actorId($request), $data['patient_id'], $data['doctor_id'], $data['laboratory_id'], $data['work_type'], $data['tooth_notes'] ?? '',
            $data['description'], $data['shade'] ?? null, $data['due_date'] ?? null, (int) $data['lab_cost_amount'], (int) $data['patient_charge_amount'],
        );

        return to_route('lab-orders.show', ['order' => $id])->with('success', 'Pesanan lab dibuat.');
    }

    public function show(Request $request, string $order): Response
    {
        return Inertia::render('Lab/Show', $this->queries->detail($order, (bool) $request->user()?->can('encounter.view'), 'lab-orders.show') + [
            'can' => ['update' => (bool) $request->user()?->can('encounter.write'), 'charge' => (bool) $request->user()?->can('invoice.edit')],
        ]);
    }

    public function send(Request $request, string $order): RedirectResponse
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']], ['date.required' => 'Tanggal kirim wajib diisi.']);
        $this->service->send($this->actorId($request), $order, $data['date']);

        return back()->with('success', 'Pesanan ditandai terkirim ke lab.');
    }

    public function receive(Request $request, string $order): RedirectResponse
    {
        $data = $request->validate(
            ['date' => ['required', 'date_format:Y-m-d'], 'lab_cost_amount' => ['required', 'integer', 'min:0', 'max:999999999999']],
            ['date.required' => 'Tanggal terima wajib diisi.', 'lab_cost_amount.required' => 'Biaya lab aktual wajib diisi.', 'lab_cost_amount.integer' => 'Biaya lab berupa bilangan bulat rupiah.'],
        );
        $this->service->receive($this->actorId($request), $order, $data['date'], (int) $data['lab_cost_amount']);

        return back()->with('success', 'Pekerjaan lab diterima.');
    }

    public function cancel(Request $request, string $order): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], ['reason.required' => 'Alasan pembatalan wajib diisi.']);
        $this->service->cancel($this->actorId($request), $order, $data['reason']);

        return back()->with('success', 'Pesanan lab dibatalkan.');
    }

    public function charge(Request $request, string $order): RedirectResponse
    {
        $data = $request->validate(['encounter_id' => ['required', 'string', 'size:26']], ['encounter_id.required' => 'Pilih kunjungan yang sedang berjalan.']);
        $this->service->charge($this->actorId($request), $order, $data['encounter_id']);

        return back()->with('success', 'Biaya lab ditambahkan ke tagihan kunjungan.');
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}

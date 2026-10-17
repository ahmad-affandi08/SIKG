<?php

declare(strict_types=1);

namespace Src\Orthodontics\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Src\Orthodontics\Application\OrthodonticQueries;
use Src\Orthodontics\Application\OrthodonticService;
use Src\Orthodontics\Domain\Appliance;

/**
 * P2-04: paket ortodonti. Izin dipasang per route: ortho.view untuk melihat, treatment_plan.manage untuk membuat, menyelesaikan,
 * dan menghentikan paket, encounter.write untuk mencatat kontrol, invoice.edit untuk menagihkan cicilan.
 */
final class OrthodonticController
{
    public function __construct(private readonly OrthodonticService $service, private readonly OrthodonticQueries $queries) {}

    public function index(Request $request): Response
    {
        $query = $request->validate(['status' => ['nullable', 'string', 'max:10']]);

        return Inertia::render('Orthodontics/Index', [
            ...$this->queries->list($query['status'] ?? null),
            'filters' => ['status' => $query['status'] ?? ''],
        ]);
    }

    public function create(Request $request): Response
    {
        $query = $request->validate(['patient' => ['required', 'string', 'size:26']]);

        return Inertia::render('Orthodontics/Create', $this->queries->createForm($query['patient']));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'string', 'size:26'],
            'doctor_id' => ['required', 'string', 'size:26'],
            'appliance' => ['required', Rule::enum(Appliance::class)],
            'title' => ['required', 'string', 'max:150'],
            'plan_notes' => ['nullable', 'string', 'max:1000'],
            'total_fee_amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'expected_months' => ['required', 'integer', 'min:1', 'max:60'],
            'installment_count' => ['required', 'integer', 'min:1', 'max:36'],
            'down_payment_amount' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
        ], [
            'doctor_id.required' => 'Dokter wajib dipilih.',
            'appliance.required' => 'Jenis alat wajib dipilih.',
            'title.required' => 'Nama paket wajib diisi.',
            'total_fee_amount.required' => 'Biaya paket wajib diisi.',
            'total_fee_amount.integer' => 'Biaya paket berupa bilangan bulat rupiah.',
            'start_date.required' => 'Tanggal mulai wajib diisi.',
            'expected_months.required' => 'Perkiraan lama perawatan wajib diisi.',
            'installment_count.required' => 'Jumlah cicilan wajib diisi.',
        ]);

        $id = $this->service->createCase(
            $this->actorId($request), $data['patient_id'], $data['doctor_id'], $data['appliance'], $data['title'], $data['plan_notes'] ?? null,
            (int) $data['total_fee_amount'], $data['start_date'], (int) $data['expected_months'], (int) $data['installment_count'], (int) ($data['down_payment_amount'] ?? 0),
        );

        return to_route('orthodontics.show', ['case' => $id])->with('success', 'Paket ortodonti dibuat beserta jadwal cicilannya.');
    }

    public function show(Request $request, string $case): Response
    {
        return Inertia::render('Orthodontics/Show', $this->queries->detail($case, (bool) $request->user()?->can('encounter.view'), 'orthodontics.show') + [
            'can' => [
                'manage' => (bool) $request->user()?->can('treatment_plan.manage'),
                'control' => (bool) $request->user()?->can('encounter.write'),
                'charge' => (bool) $request->user()?->can('invoice.edit'),
            ],
        ]);
    }

    public function addControl(Request $request, string $case): RedirectResponse
    {
        $data = $request->validate([
            'control_date' => ['required', 'date_format:Y-m-d'],
            'encounter_id' => ['nullable', 'string', 'size:26'],
            'wire_or_tray' => ['nullable', 'string', 'max:100'],
            'adjustments' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'next_control_date' => ['nullable', 'date_format:Y-m-d'],
        ], ['control_date.required' => 'Tanggal kontrol wajib diisi.']);

        $this->service->addControl(
            $this->actorId($request), $case, $data['control_date'], $data['encounter_id'] ?? null, $data['wire_or_tray'] ?? null,
            $data['adjustments'] ?? null, $data['notes'] ?? null, $data['next_control_date'] ?? null,
        );

        return back()->with('success', 'Catatan kontrol disimpan.');
    }

    public function complete(Request $request, string $case): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:255']]);
        $this->service->complete($this->actorId($request), $case, $data['note'] ?? null);

        return back()->with('success', 'Perawatan ortodonti diselesaikan.');
    }

    public function stop(Request $request, string $case): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], ['reason.required' => 'Alasan menghentikan perawatan wajib diisi.']);
        $this->service->stop($this->actorId($request), $case, $data['reason']);

        return back()->with('success', 'Perawatan dihentikan. Cicilan yang belum ditagihkan dibatalkan.');
    }

    public function charge(Request $request, string $item): RedirectResponse
    {
        $data = $request->validate(['encounter_id' => ['required', 'string', 'size:26']], ['encounter_id.required' => 'Pilih kunjungan yang sedang berjalan.']);
        $this->service->chargeInstallment($this->actorId($request), $item, $data['encounter_id']);

        return back()->with('success', 'Cicilan ditambahkan ke tagihan kunjungan.');
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}

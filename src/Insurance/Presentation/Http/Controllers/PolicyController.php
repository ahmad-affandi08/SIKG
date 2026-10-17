<?php

declare(strict_types=1);

namespace Src\Insurance\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Insurance\Application\PolicyService;
use Src\Insurance\Presentation\Http\Requests\PolicyRequest;

/**
 * P2-02: polis penjamin pasien. Melihat butuh patient.view, mengubah butuh patient.update (dipasang di route).
 */
final class PolicyController
{
    public function __construct(private readonly PolicyService $policies) {}

    public function index(Request $request, string $patient): Response
    {
        return Inertia::render('Patients/Insurance', [
            ...$this->policies->forPatient($patient, 'patients.insurance'),
            'canManage' => (bool) $request->user()?->can('patient.update'),
        ]);
    }

    public function store(PolicyRequest $request, string $patient): RedirectResponse
    {
        $this->policies->create($this->actorId($request), $patient, ...$this->fields($request));

        return back()->with('success', 'Polis penjamin ditambahkan.');
    }

    public function update(PolicyRequest $request, string $patient, string $policy): RedirectResponse
    {
        $this->policies->update($this->actorId($request), $policy, ...$this->fields($request));

        return back()->with('success', 'Polis penjamin disimpan.');
    }

    public function deactivate(Request $request, string $patient, string $policy): RedirectResponse
    {
        $this->policies->setActive($this->actorId($request), $policy, false);

        return back()->with('success', 'Polis dinonaktifkan. Riwayat klaim tetap tersimpan.');
    }

    public function activate(Request $request, string $patient, string $policy): RedirectResponse
    {
        $this->policies->setActive($this->actorId($request), $policy, true);

        return back()->with('success', 'Polis diaktifkan kembali.');
    }

    /**
     * @return array{insurerId: string, policyNo: string, ceilingAmount: int|null, validFrom: string|null, validTo: string|null, notes: string|null}
     */
    private function fields(PolicyRequest $request): array
    {
        $text = fn (string $key): ?string => is_string($request->input($key)) && $request->input($key) !== '' ? $request->input($key) : null;
        $ceiling = $request->input('ceiling_amount');

        return [
            'insurerId' => $request->string('insurer_id')->toString(),
            'policyNo' => $request->string('policy_no')->toString(),
            'ceilingAmount' => $ceiling === null || $ceiling === '' ? null : (int) $ceiling,
            'validFrom' => $text('valid_from'),
            'validTo' => $text('valid_to'),
            'notes' => $text('notes'),
        ];
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}

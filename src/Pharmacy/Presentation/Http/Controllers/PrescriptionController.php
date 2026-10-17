<?php

declare(strict_types=1);

namespace Src\Pharmacy\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Src\Clinical\Contracts\EncounterLookup;
use Src\Patient\Contracts\PatientLookup;
use Src\Pharmacy\Application\PrescriptionPrint;
use Src\Pharmacy\Application\PrescriptionService;
use Src\Pharmacy\Domain\PrescriptionItemInput;
use Src\Shared\Application\PdfRenderer;
use Src\Shared\Application\PdfRequest;
use Src\Shared\Application\RequiredFields;
use Symfony\Component\HttpFoundation\Response;

/**
 * OBT-01 sampai OBT-04. Izin dipasang per route: prescription.create, prescription.update, prescription.view.
 */
final class PrescriptionController
{
    /**
     * Halaman formulir resep baru. Resep hanya dibuat selama kunjungan berjalan.
     */
    public function create(Request $request, string $encounter, EncounterLookup $encounters, PatientLookup $patients): \Inertia\Response|RedirectResponse
    {
        $info = $encounters->find($encounter);
        abort_if($info === null, 404);
        if (! $info->open) {
            return to_route('encounters.show', ['encounter' => $encounter, 'tab' => 'prescriptions'])->with('error', 'Kunjungan sudah ditutup. Resep baru tidak dapat dibuat.');
        }
        $patient = $patients->summaries([$info->patientId])[$info->patientId] ?? null;

        return Inertia::render('Encounters/PrescriptionForm', [
            'required' => RequiredFields::from(self::rules()),
            'encounter' => ['id' => $encounter, 'patient_name' => $patient?->name, 'medical_record_no' => $patient?->medicalRecordNo],
            'can' => ['overrideStock' => (bool) $request->user()?->can('stock.override')],
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    private static function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.medicine_id' => ['nullable', 'string', 'size:26'],
            'items.*.free_text_name' => ['nullable', 'string', 'max:255'],
            'items.*.dose' => ['required', 'string', 'max:100'],
            'items.*.frequency' => ['required', 'string', 'max:100'],
            'items.*.duration' => ['required', 'string', 'max:100'],
            'items.*.instructions' => ['nullable', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'integer', 'between:1,9999'],
            'items.*.dispense_in_clinic' => ['sometimes', 'boolean'],
            'allergy_acknowledgement' => ['nullable', 'string', 'max:500'],
            'stock_override_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function store(Request $request, string $encounter, PrescriptionService $prescriptions): RedirectResponse
    {
        $data = $request->validate(self::rules(), [
            'items.required' => 'Resep minimal berisi satu obat.',
            'items.*.dose.required' => 'Dosis wajib diisi.',
            'items.*.frequency.required' => 'Frekuensi wajib diisi.',
            'items.*.duration.required' => 'Durasi wajib diisi.',
            'items.*.quantity.required' => 'Jumlah wajib diisi.',
        ]);

        $items = array_map(fn (array $i) => new PrescriptionItemInput(
            $i['medicine_id'] ?? null,
            $i['free_text_name'] ?? null,
            $i['dose'],
            $i['frequency'],
            $i['duration'],
            $i['instructions'] ?? null,
            (int) $i['quantity'],
            (bool) ($i['dispense_in_clinic'] ?? false),
        ), array_values($data['items']));

        // Override stok hanya dipakai pengguna yang berhak (Admin); selain itu diabaikan sehingga stok kurang tetap ditolak.
        $override = $request->user()?->can('stock.override') === true ? ($data['stock_override_reason'] ?? null) : null;

        $prescriptions->create($this->actorId($request), $encounter, $items, $data['allergy_acknowledgement'] ?? null, $override);

        return to_route('encounters.show', ['encounter' => $encounter, 'tab' => 'prescriptions'])->with('success', 'Resep disimpan.');
    }

    public function void(Request $request, string $prescription, PrescriptionService $prescriptions): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], ['reason.required' => 'Alasan pembatalan resep wajib diisi.']);
        $prescriptions->void($this->actorId($request), $prescription, $data['reason']);

        return back()->with('success', 'Resep dibatalkan.');
    }

    public function print(Request $request, string $prescription, PrescriptionPrint $print, PdfRenderer $renderer): Response
    {
        $data = $print->data($this->actorId($request), $prescription);

        return response($renderer->render(new PdfRequest('pdf.prescription', $data, 'Resep '.$data['patient']['name'], customSizePt: [419.53, 595.28], pageNumbers: false)), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="resep.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}

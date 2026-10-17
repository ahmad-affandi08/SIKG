<?php

declare(strict_types=1);

namespace Src\Clinical\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Src\Clinical\Application\ClinicalDocuments;
use Src\Clinical\Application\DocumentSigning;
use Src\Clinical\Domain\DocumentType;
use Src\Shared\Application\PdfRenderer;
use Src\Shared\Application\PdfRequest;
use Src\Shared\Application\RequiredFields;
use Symfony\Component\HttpFoundation\Response;

/**
 * RM-09 dan RM-10: informed consent, surat keterangan, dan surat rujukan dicetak dari templat untuk tanda tangan basah.
 * Melihat dan mencetak butuh encounter.view; menerbitkan butuh izin menurut jenis (consent: encounter.write atau record.print;
 * surat: encounter.close atau record.print).
 */
final class DocumentController
{
    public function index(Request $request, string $patient, ClinicalDocuments $documents): InertiaResponse
    {
        $user = $request->user();

        return Inertia::render('Documents/Index', [
            ...$documents->overview($patient, 'documents.index'),
            'can' => [
                'consent' => (bool) $user?->can('documents.consent'),
                'letter' => (bool) $user?->can('documents.letter'),
            ],
            'types' => array_map(fn (DocumentType $t) => ['value' => $t->value, 'label' => $t->label()], DocumentType::cases()),
        ]);
    }

    public function create(Request $request, string $patient, ClinicalDocuments $documents): InertiaResponse
    {
        $type = $this->type($request->query('type'));
        $this->authorizeType($request, $type);

        return Inertia::render('Documents/Form', [
            'required' => RequiredFields::from(self::rules()),
            ...$documents->formData($patient, $type, 'documents.create'),
        ]);
    }

    public function store(Request $request, string $patient, ClinicalDocuments $documents): RedirectResponse
    {
        $data = $request->validate(self::rules(), [
            'type.required' => 'Jenis dokumen wajib dipilih.',
            'doctor_id.required' => 'Dokter wajib dipilih.',
            'body.required' => 'Isi dokumen wajib diisi.',
            'body.max' => 'Isi dokumen terlalu panjang.',
        ]);
        $type = $this->type($data['type']);
        $this->authorizeType($request, $type);

        $documents->issue($this->actorId($request), $patient, $type, $data['template_id'] ?? null, $data['encounter_id'] ?? null, $data['doctor_id'], $data['title'] ?? null, $data['body']);

        return to_route('documents.index', ['patient' => $patient])->with('success', $type->label().' diterbitkan. Cetak untuk tanda tangan.');
    }

    public function print(Request $request, string $document, ClinicalDocuments $documents, PdfRenderer $pdf): Response
    {
        $data = $documents->printData($this->actorId($request), $document);
        $output = $pdf->render(new PdfRequest('pdf.document', $data, $data['heading'].' '.$data['number']));

        return response($output, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $data['file_name']).'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function signForm(string $document, DocumentSigning $signing): InertiaResponse
    {
        return Inertia::render('Documents/Sign', $signing->page($document));
    }

    public function sign(Request $request, string $document, DocumentSigning $signing, ClinicalDocuments $documents): RedirectResponse
    {
        $data = $request->validate([
            'signer_name' => ['required', 'string', 'max:150'],
            'signer_role' => ['required', 'in:patient,guardian'],
            'signature' => ['required', 'string', 'max:700000'],
            'witness_name' => ['nullable', 'string', 'max:150'],
            'witness_signature' => ['nullable', 'string', 'max:700000'],
        ], [
            'signer_name.required' => 'Nama penanda tangan wajib diisi.',
            'signer_role.required' => 'Pilih pasien atau wali pasien.',
            'signature.required' => 'Tanda tangan wajib diisi.',
        ]);

        $signing->sign($this->actorId($request), $document, $data['signer_name'], $data['signer_role'], $data['witness_name'] ?? null, $data['signature'], $data['witness_signature'] ?? null);
        $patientId = $documents->documentOf($document)['patient_id'];

        return to_route('documents.index', ['patient' => $patientId])->with('success', 'Dokumen ditandatangani dan PDF tersimpan di rekam medis.');
    }

    public function signed(Request $request, string $document, DocumentSigning $signing): Response
    {
        $file = $signing->signedPdf($this->actorId($request), $document);

        return response($file['contents'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.preg_replace('/[^A-Za-z0-9._-]/', '_', $file['file_name']).'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    private static function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(DocumentType::class)],
            'template_id' => ['nullable', 'string', 'size:26'],
            'encounter_id' => ['nullable', 'string', 'size:26'],
            'doctor_id' => ['required', 'string', 'size:26'],
            'title' => ['nullable', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:'.ClinicalDocuments::MAX_BODY],
        ];
    }

    private function type(mixed $value): DocumentType
    {
        $type = is_string($value) ? DocumentType::tryFrom($value) : null;
        abort_if($type === null, 404);

        return $type;
    }

    private function authorizeType(Request $request, DocumentType $type): void
    {
        $gate = $type === DocumentType::Consent ? 'documents.consent' : 'documents.letter';
        abort_unless((bool) $request->user()?->can($gate), 403);
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}

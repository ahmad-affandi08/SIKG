<?php

declare(strict_types=1);

namespace Src\Patient\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Patient\Application\Import\CommitPatientImport;
use Src\Patient\Application\Import\SpreadsheetReader;
use Src\Patient\Application\Import\StartPatientImport;
use Src\Patient\Domain\Import\ImportBatchRepository;
use Src\Patient\Domain\PatientNotFound;
use Src\Patient\Presentation\Http\Requests\PatientImportRequest;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * PAS-08: impor pasien dari Excel dengan pratinjau. Semua route di balik can:patient.import.
 */
final class PatientImportController
{
    public function create(): Response
    {
        return Inertia::render('Patients/Import', ['batch' => null]);
    }

    public function template(SpreadsheetReader $reader): HttpResponse
    {
        return response($reader->template(), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="templat-impor-pasien.xlsx"',
        ]);
    }

    public function store(PatientImportRequest $request, StartPatientImport $start): RedirectResponse
    {
        $file = $request->file('file');
        abort_if($file === null, 422);

        $batchId = $start($this->actorId($request), $file->getContent());

        return to_route('patients.import.show', ['batch' => $batchId]);
    }

    public function show(string $batch, ImportBatchRepository $batches): Response
    {
        $found = $batches->find($batch) ?? throw new PatientNotFound;

        return Inertia::render('Patients/Import', ['batch' => $found->toArray()]);
    }

    public function commit(Request $request, string $batch, CommitPatientImport $commit): RedirectResponse
    {
        $commit($this->actorId($request), $batch, $request->boolean('include_duplicates'));

        return to_route('patients.import.show', ['batch' => $batch])->with('success', 'Impor dimulai.');
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}

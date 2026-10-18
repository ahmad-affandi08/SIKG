<?php

declare(strict_types=1);

namespace Src\Clinical\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Clinical\Application\Imaging\ImagingService;

/**
 * P3-03: rontgen digital. Folder masuk, impor DICOM langsung, dan pembuangan berkas masuk; izin encounter.write dipasang di route.
 */
final class ImagingController
{
    public function __construct(private readonly ImagingService $imaging) {}

    public function inbox(): Response
    {
        return Inertia::render('Imaging/Inbox', $this->imaging->overview());
    }

    public function importFromInbox(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'encounter_id' => ['required', 'string', 'size:26'],
            'tooth_fdi' => ['nullable', 'integer'],
            'confirm_mismatch' => ['nullable', 'boolean'],
        ], ['name.required' => 'Pilih berkas.', 'encounter_id.required' => 'Pilih kunjungan tujuan.']);
        $this->imaging->importFromInbox($this->actorId($request), $data['name'], $data['encounter_id'], (bool) ($data['confirm_mismatch'] ?? false), isset($data['tooth_fdi']) ? (int) $data['tooth_fdi'] : null);

        return back()->with('success', 'Gambar dilampirkan ke kunjungan.');
    }

    public function discard(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']], ['name.required' => 'Pilih berkas.']);
        $this->imaging->discardFromInbox($this->actorId($request), $data['name']);

        return back()->with('success', 'Berkas dipindah ke arsip ditolak.');
    }

    public function uploadDicom(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.(ImagingService::MAX_DICOM_BYTES / 1024)],
            'encounter_id' => ['required', 'string', 'size:26'],
            'tooth_fdi' => ['nullable', 'integer'],
            'caption' => ['nullable', 'string', 'max:255'],
            'confirm_mismatch' => ['nullable', 'boolean'],
        ], ['file.required' => 'Pilih berkas DICOM.', 'file.max' => 'Ukuran berkas maksimal 40 MB.', 'encounter_id.required' => 'Pilih kunjungan tujuan.']);
        $upload = $request->file('file');
        abort_if($upload === null, 422);

        $this->imaging->importDicom($this->actorId($request), $data['encounter_id'], $upload->getContent(), (bool) ($data['confirm_mismatch'] ?? false), isset($data['tooth_fdi']) ? (int) $data['tooth_fdi'] : null, $data['caption'] ?? null);

        return to_route('encounters.show', ['encounter' => $data['encounter_id'], 'tab' => 'files'])->with('success', 'Gambar DICOM diimpor.');
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}

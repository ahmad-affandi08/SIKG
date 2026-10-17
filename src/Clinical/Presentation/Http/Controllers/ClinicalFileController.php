<?php

declare(strict_types=1);

namespace Src\Clinical\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Src\Clinical\Application\ClinicalFiles;
use Src\Clinical\Domain\FileKind;
use Symfony\Component\HttpFoundation\Response;

/**
 * RM-08. Berkas disajikan hanya lewat controller ini (disk privat, tanpa URL publik) dengan jenis isi yang dideteksi dari isinya.
 */
final class ClinicalFileController
{
    /**
     * @return array<string, list<string>>
     */
    public static function uploadRules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.(ClinicalFiles::MAX_BYTES / 1024), 'mimetypes:'.implode(',', array_keys(ClinicalFiles::TYPES))],
            'kind' => ['required', 'in:'.implode(',', array_map(fn (FileKind $k) => $k->value, FileKind::cases()))],
            'tooth_fdi' => ['nullable', 'integer'],
            'caption' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function store(Request $request, string $encounter, ClinicalFiles $files): RedirectResponse
    {
        $data = $request->validate(self::uploadRules(), [
            'file.required' => 'Pilih berkas.',
            'file.max' => 'Ukuran berkas maksimal 10 MB.',
            'file.mimetypes' => 'Berkas harus berformat JPG, PNG, atau PDF.',
            'kind.required' => 'Jenis berkas wajib dipilih.',
        ]);

        $upload = $request->file('file');
        abort_if($upload === null, 422);

        $files->upload($this->actorId($request), $encounter, $upload->getContent(), (string) $upload->getMimeType(), $data['kind'], $data['tooth_fdi'] ?? null, $data['caption'] ?? null);

        return to_route('encounters.show', ['encounter' => $encounter, 'tab' => 'files'])->with('success', 'Berkas diunggah.');
    }

    public function destroy(Request $request, string $encounter, string $file, ClinicalFiles $files): RedirectResponse
    {
        $files->remove($this->actorId($request), $encounter, $file);

        return back()->with('success', 'Berkas dilepas dari kunjungan.');
    }

    public function show(string $file, ClinicalFiles $files): Response
    {
        $opened = $files->open($file, 'clinical-files.show');

        $headers = [
            'Content-Type' => $opened['file']->mime,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store',
        ];
        // Gambar disajikan tanpa sumber daya lain; PDF memakai kebijakan bawaan agar penampil PDF peramban tetap berfungsi.
        if (str_starts_with($opened['file']->mime, 'image/')) {
            $headers['Content-Security-Policy'] = "default-src 'none'; style-src 'unsafe-inline'; sandbox";
        }

        return response($opened['contents'], 200, $headers);
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}

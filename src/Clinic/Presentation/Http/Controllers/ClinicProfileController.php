<?php

declare(strict_types=1);

namespace Src\Clinic\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Src\Clinic\Application\Profile\ClinicProfile;
use Src\Clinic\Application\Profile\ClinicProfileService;
use Src\Clinic\Presentation\Http\Requests\ClinicLogoRequest;
use Src\Clinic\Presentation\Http\Requests\ClinicProfileRequest;
use Src\Shared\Application\RequiredFields;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * MST-01: profil klinik, logo, header kwitansi dan resep. Semua route di balik can:setting.manage.
 */
final class ClinicProfileController
{
    public function __construct(private readonly ClinicProfileService $profile) {}

    public function show(Request $request): Response
    {
        return Inertia::render('Settings/ClinicProfile', [
            'required' => RequiredFields::from(ClinicProfileRequest::createFrom($request)->rules()),
            'profile' => $this->profile->get()->toArray(),
        ]);
    }

    public function update(ClinicProfileRequest $request): RedirectResponse
    {
        $this->profile->update($this->actorId($request), new ClinicProfile(
            name: $request->string('name')->trim()->toString(),
            address: $this->nullable($request->input('address')),
            city: $this->nullable($request->input('city')),
            licenseNo: $this->nullable($request->input('license_no')),
            receiptHeader: $this->nullable($request->input('receipt_header')),
            prescriptionHeader: $this->nullable($request->input('prescription_header')),
        ));

        return back()->with('success', 'Profil klinik disimpan.');
    }

    public function uploadLogo(ClinicLogoRequest $request): RedirectResponse
    {
        $file = $request->file('logo');
        abort_if($file === null, 422);

        $extension = $file->getMimeType() === 'image/png' ? 'png' : 'jpg';
        $this->profile->replaceLogo($this->actorId($request), $file->getContent(), $extension);

        return back()->with('success', 'Logo diperbarui.');
    }

    public function removeLogo(Request $request): RedirectResponse
    {
        $this->profile->removeLogo($this->actorId($request));

        return back()->with('success', 'Logo dihapus.');
    }

    public function logo(): StreamedResponse
    {
        $logo = $this->profile->logo();
        abort_if($logo === null, 404);

        return Storage::disk('private')->response($logo['path'], null, [
            'Content-Type' => $logo['mime'],
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    private function nullable(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}

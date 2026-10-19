<?php

declare(strict_types=1);

namespace Src\Clinic\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Src\Clinic\Application\Settings\ClinicSettingsService;
use Src\Clinic\Contracts\ServiceProfileLookup;
use Src\Shared\Domain\ServiceProfile;

/** KLU-01: pilihan profil layanan klinik (gigi atau umum). Hanya pemegang izin pengaturan. */
final class ServiceProfileController
{
    public function __construct(private readonly ClinicSettingsService $settings, private readonly ServiceProfileLookup $lookup) {}

    public function show(): Response
    {
        return Inertia::render('Settings/ServiceProfile', [
            'current' => $this->lookup->current()->value,
            'options' => array_map(fn (ServiceProfile $p) => ['value' => $p->value, 'label' => $p->label(), 'description' => $p->description()], ServiceProfile::cases()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'profile' => ['required', 'string', Rule::in(array_map(fn (ServiceProfile $p) => $p->value, ServiceProfile::cases()))],
        ], ['profile.in' => 'Profil layanan tidak dikenal.']);

        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);
        $this->settings->changeServiceProfile($id, ServiceProfile::from((string) $data['profile']));

        return back()->with('success', 'Profil layanan disimpan.');
    }
}

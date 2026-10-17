<?php

declare(strict_types=1);

namespace Src\Integration\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Integration\Application\SatuSehatAdministration;
use Src\Integration\Presentation\Http\Requests\SatuSehatSettingsRequest;

/**
 * P2-01: pengaturan SATUSEHAT, NIK dokter, uji koneksi, dan pemantauan pengiriman. Izin setting.manage dipasang di route.
 * Client secret tidak pernah dikirim balik ke layar.
 */
final class SatuSehatController
{
    public function __construct(private readonly SatuSehatAdministration $admin) {}

    public function index(): Response
    {
        return Inertia::render('Settings/Satusehat/Index', $this->admin->overview());
    }

    public function update(SatuSehatSettingsRequest $request): RedirectResponse
    {
        $this->admin->saveSettings(
            $this->actorId($request),
            $request->boolean('enabled'),
            $request->string('environment')->toString(),
            $this->nullable($request, 'organization_id'),
            $this->nullable($request, 'location_id'),
            $this->nullable($request, 'location_name'),
            $this->nullable($request, 'client_id'),
            $this->nullable($request, 'client_secret'),
        );

        return back()->with('success', 'Pengaturan SATUSEHAT disimpan.');
    }

    public function test(Request $request): RedirectResponse
    {
        $result = $this->admin->testConnection($this->actorId($request));

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function practitioner(Request $request, string $doctor): RedirectResponse
    {
        $data = $request->validate(['nik' => ['nullable', 'string', 'max:32']], ['nik.max' => 'NIK terlalu panjang.']);
        $this->admin->savePractitionerNik($this->actorId($request), $doctor, $data['nik'] ?? null);

        return back()->with('success', 'NIK dokter disimpan.');
    }

    public function retry(Request $request, string $submission): RedirectResponse
    {
        $this->admin->retry($this->actorId($request), $submission);

        return back()->with('success', 'Pengiriman dimasukkan kembali ke antrean.');
    }

    private function nullable(Request $request, string $key): ?string
    {
        $value = $request->input($key);

        return is_string($value) ? $value : null;
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}

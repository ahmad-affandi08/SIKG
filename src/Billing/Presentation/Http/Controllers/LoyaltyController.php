<?php

declare(strict_types=1);

namespace Src\Billing\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Billing\Application\Loyalty\LoyaltyQueries;
use Src\Billing\Application\Loyalty\LoyaltyService;
use Src\Billing\Application\Loyalty\PackageService;

/**
 * P3-06: poin dan paket kunjungan pasien serta pengaturan poin. Melihat: patient.view; menjual paket: invoice.edit; pengaturan:
 * setting.manage (dipasang di route).
 */
final class LoyaltyController
{
    public function __construct(private readonly LoyaltyQueries $queries, private readonly LoyaltyService $loyalty, private readonly PackageService $packages) {}

    public function patient(Request $request, string $patient): Response
    {
        return Inertia::render('Patients/Loyalty', [...$this->queries->patientOverview($patient), 'can' => ['sell' => (bool) $request->user()?->can('invoice.edit')]]);
    }

    public function sell(Request $request, string $patient): RedirectResponse
    {
        $data = $request->validate(
            ['package_id' => ['required', 'string', 'size:26'], 'encounter_id' => ['required', 'string', 'size:26']],
            ['package_id.required' => 'Pilih paket.', 'encounter_id.required' => 'Pilih kunjungan yang sedang berjalan.'],
        );
        $this->packages->sell($this->actorId($request), $patient, $data['package_id'], $data['encounter_id']);

        return back()->with('success', 'Paket dibeli dan ditambahkan ke tagihan kunjungan.');
    }

    public function settings(): Response
    {
        return Inertia::render('Settings/Loyalty', ['settings' => $this->queries->settings()]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'rupiah_per_point' => ['required', 'integer', 'min:1', 'max:100000000'],
            'point_value_amount' => ['required', 'integer', 'min:1', 'max:100000000'],
            'min_redeem_points' => ['required', 'integer', 'min:1', 'max:100000'],
        ], [
            'rupiah_per_point.required' => 'Belanja per poin wajib diisi.', 'rupiah_per_point.integer' => 'Belanja per poin berupa rupiah bulat.',
            'point_value_amount.required' => 'Nilai satu poin wajib diisi.', 'point_value_amount.integer' => 'Nilai satu poin berupa rupiah bulat.',
            'min_redeem_points.required' => 'Minimum penukaran wajib diisi.',
        ]);
        $this->loyalty->saveSettings($this->actorId($request), (bool) $data['enabled'], (int) $data['rupiah_per_point'], (int) $data['point_value_amount'], (int) $data['min_redeem_points']);

        return back()->with('success', 'Pengaturan poin disimpan.');
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}

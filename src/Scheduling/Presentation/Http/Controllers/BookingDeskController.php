<?php

declare(strict_types=1);

namespace Src\Scheduling\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Scheduling\Application\Booking\BookingAdministration;
use Src\Scheduling\Application\Booking\BookingDesk;
use Src\Scheduling\Domain\Booking\BookingSettings;

/**
 * P3-01: meja staf (appointment.manage) dan pengaturan booking online (setting.manage); izin dipasang di route.
 */
final class BookingDeskController
{
    public function __construct(private readonly BookingDesk $desk, private readonly BookingAdministration $admin) {}

    public function index(): Response
    {
        return Inertia::render('Booking/Requests/Index', $this->desk->overview());
    }

    public function phone(Request $request, string $id): JsonResponse
    {
        return response()->json(['data' => ['phone' => $this->desk->revealPhone($this->actorId($request), $id)], 'meta' => []])->header('Cache-Control', 'no-store');
    }

    public function confirm(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate(['patient_id' => ['required', 'string', 'size:26'], 'contacted' => ['nullable', 'boolean']], ['patient_id.required' => 'Pilih pasien terdaftar.']);
        $this->desk->confirm($this->actorId($request), $id, $data['patient_id'], (bool) ($data['contacted'] ?? false));

        return back()->with('success', 'Booking dikonfirmasi dan masuk ke jadwal.');
    }

    public function reject(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], ['reason.required' => 'Alasan penolakan wajib diisi.']);
        $this->desk->reject($this->actorId($request), $id, $data['reason']);

        return back()->with('success', 'Permintaan ditolak.');
    }

    public function settings(): Response
    {
        return Inertia::render('Settings/Booking', ['settings' => $this->admin->settings()]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'slug' => ['nullable', 'string', 'max:60'],
            'slot_minutes' => ['required', 'integer', 'min:15', 'max:180'],
            'horizon_days' => ['required', 'integer', 'min:1', 'max:90'],
            'min_lead_hours' => ['required', 'integer', 'min:0', 'max:72'],
            'hold_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'max_active_per_phone' => ['required', 'integer', 'min:1', 'max:10'],
            'max_requests_per_hour' => ['nullable', 'integer', 'min:5', 'max:500'],
            'otp_template' => ['nullable', 'string', 'max:100'],
            'welcome_text' => ['nullable', 'string', 'max:500'],
        ], [
            'slot_minutes.required' => 'Durasi slot wajib diisi.', 'horizon_days.required' => 'Batas hari wajib diisi.', 'min_lead_hours.required' => 'Jeda minimum wajib diisi.',
            'hold_hours.required' => 'Masa tahan wajib diisi.', 'max_active_per_phone.required' => 'Batas per nomor wajib diisi.', 'max_requests_per_hour.min' => 'Batas permintaan per jam minimal 5.', 'max_requests_per_hour.max' => 'Batas permintaan per jam maksimal 500.', 'max_requests_per_hour.integer' => 'Batas permintaan per jam berupa bilangan bulat.',
        ]);
        $this->admin->save($this->actorId($request), new BookingSettings(
            (bool) $data['enabled'], strtolower(trim((string) ($data['slug'] ?? ''))), (int) $data['slot_minutes'], (int) $data['horizon_days'], (int) $data['min_lead_hours'],
            (int) $data['hold_hours'], (int) $data['max_active_per_phone'], $this->clean($data['otp_template'] ?? null), $this->clean($data['welcome_text'] ?? null),
            (int) ($data['max_requests_per_hour'] ?? 30),
        ));

        return back()->with('success', 'Pengaturan booking online disimpan.');
    }

    private function clean(?string $text): ?string
    {
        return $text === null || trim($text) === '' ? null : trim($text);
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}

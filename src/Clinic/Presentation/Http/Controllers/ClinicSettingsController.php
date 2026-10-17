<?php

declare(strict_types=1);

namespace Src\Clinic\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Src\Clinic\Application\Settings\ClinicSettingsService;
use Src\Clinic\Domain\ClinicSettings;
use Src\Clinic\Presentation\Http\Requests\HolidayRequest;
use Src\Clinic\Presentation\Http\Requests\UpdateClinicSettingsRequest;
use Src\Shared\Domain\NumberPattern;

final class ClinicSettingsController
{
    public function __construct(private readonly ClinicSettingsService $settings) {}

    public function show(): Response
    {
        return Inertia::render('Settings/Clinic', [
            ...$this->settings->overview(),
            'sessionMaxMinutes' => (int) config('sikg.session_max_minutes'),
        ]);
    }

    public function update(UpdateClinicSettingsRequest $request): RedirectResponse
    {
        /** @var array<string, mixed> $data */
        $data = $request->validated();

        try {
            $settings = new ClinicSettings(
                sessionTimeoutMinutes: (int) $data['session_timeout_minutes'],
                medicalRecordPattern: new NumberPattern((string) $data['medical_record_pattern']),
                invoicePattern: new NumberPattern((string) $data['invoice_pattern']),
                operatingHours: $this->hours((array) $data['operating_hours']),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['settings' => $e->getMessage()]);
        }

        $this->settings->update($this->actorId($request), (string) $data['timezone'], $settings);

        return back()->with('success', 'Pengaturan klinik disimpan.');
    }

    public function storeHoliday(HolidayRequest $request): RedirectResponse
    {
        $this->settings->addHoliday($this->actorId($request), $request->string('date')->toString(), $request->string('name')->toString());

        return back()->with('success', 'Hari libur ditambahkan.');
    }

    public function destroyHoliday(Request $request, string $holiday): RedirectResponse
    {
        $this->settings->removeHoliday($this->actorId($request), $holiday);

        return back()->with('success', 'Hari libur dihapus.');
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }

    /**
     * @param  array<int|string, mixed>  $input
     * @return array<int, array{open: string, close: string}|null>
     */
    private function hours(array $input): array
    {
        $hours = [];
        foreach ($input as $day => $value) {
            $hours[(int) $day] = is_array($value) && isset($value['open'], $value['close'])
                ? ['open' => (string) $value['open'], 'close' => (string) $value['close']]
                : null;
        }

        return $hours;
    }
}

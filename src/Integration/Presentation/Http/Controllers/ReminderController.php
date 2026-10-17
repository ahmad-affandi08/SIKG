<?php

declare(strict_types=1);

namespace Src\Integration\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Src\Integration\Application\Reminder\ReminderAdministration;
use Src\Integration\Application\Reminder\ReminderDesk;
use Src\Integration\Domain\Reminder\ReminderSettings;

/**
 * P2-03: meja pengingat (izin appointment.manage) dan pengaturan pengingat (izin setting.manage), dipasang di route.
 */
final class ReminderController
{
    public function __construct(private readonly ReminderDesk $desk, private readonly ReminderAdministration $admin) {}

    public function index(): Response
    {
        return Inertia::render('Reminders/Index', $this->desk->overview());
    }

    public function link(Request $request, string $reminder): JsonResponse
    {
        return response()->json(['data' => $this->desk->link($this->actorId($request), $reminder, 'reminders.link'), 'meta' => (object) []])
            ->header('Cache-Control', 'private, no-store');
    }

    public function markSent(Request $request, string $reminder): RedirectResponse
    {
        $this->desk->markSent($this->actorId($request), $reminder);

        return back()->with('success', 'Pengingat ditandai terkirim.');
    }

    public function skip(Request $request, string $reminder): RedirectResponse
    {
        $this->desk->skip($this->actorId($request), $reminder);

        return back()->with('success', 'Pengingat dilewati.');
    }

    public function optOut(Request $request, string $reminder): RedirectResponse
    {
        $this->desk->optOut($this->actorId($request), $reminder);

        return back()->with('success', 'Pasien tidak akan diingatkan lagi.');
    }

    public function optIn(Request $request, string $patient): RedirectResponse
    {
        $this->desk->optIn($this->actorId($request), $patient);

        return back()->with('success', 'Pasien diingatkan kembali.');
    }

    public function settings(): Response
    {
        return Inertia::render('Settings/Reminders', $this->admin->overview());
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'mode' => ['required', Rule::in([ReminderSettings::MODE_LINK, ReminderSettings::MODE_API])],
            'recall_months' => ['required', 'integer', 'min:1', 'max:24'],
            'api_phone_number_id' => ['nullable', 'string', 'max:64', 'regex:/^[0-9]+$/'],
            'api_token' => ['nullable', 'string', 'max:512'],
            'api_language' => ['nullable', 'string', 'max:10', 'regex:/^[a-z]{2}(_[A-Z]{2})?$/'],
            'kinds' => ['required', 'array'],
            'kinds.*.enabled' => ['required', 'boolean'],
            'kinds.*.template' => ['nullable', 'string', 'max:500'],
            'kinds.*.api_template' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
        ], [
            'api_phone_number_id.regex' => 'ID nomor telepon hanya angka.',
            'api_language.regex' => 'Kode bahasa berbentuk id atau en_US.',
            'kinds.*.api_template.regex' => 'Nama templat hanya huruf kecil, angka, dan garis bawah.',
            'kinds.*.template.max' => 'Templat pesan maksimal 500 karakter.',
            'recall_months.min' => 'Jarak kontrol berkala 1 sampai 24 bulan.',
            'recall_months.max' => 'Jarak kontrol berkala 1 sampai 24 bulan.',
        ]);

        $kinds = [];
        foreach ((array) $data['kinds'] as $kind => $input) {
            $kinds[(string) $kind] = ['enabled' => (bool) $input['enabled'], 'template' => $input['template'] ?? null, 'api_template' => $input['api_template'] ?? null];
        }
        $this->admin->save(
            $this->actorId($request), (bool) $data['enabled'], (string) $data['mode'], (int) $data['recall_months'],
            $data['api_phone_number_id'] ?? null, $data['api_token'] ?? null, (string) ($data['api_language'] ?? 'id'), $kinds,
        );

        return back()->with('success', 'Pengaturan pengingat disimpan.');
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}

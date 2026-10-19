<?php

declare(strict_types=1);

namespace Src\Integration\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Src\Integration\Application\Reminder\WhatsAppAdministration;
use Src\Integration\Domain\Reminder\WhatsAppProvider;

/**
 * Pengaturan saluran WhatsApp (izin setting.manage dipasang di route). Token tidak pernah dikirim balik ke layar.
 */
final class WhatsAppSettingsController
{
    public function __construct(private readonly WhatsAppAdministration $admin) {}

    public function index(): Response
    {
        return Inertia::render('Settings/WhatsApp', $this->admin->overview());
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', Rule::in(array_map(fn (WhatsAppProvider $p) => $p->value, WhatsAppProvider::cases()))],
            'phone_number_id' => ['nullable', 'string', 'max:64', 'regex:/^[0-9]+$/'],
            'token' => ['nullable', 'string', 'max:512', 'regex:/^[\x21-\x7E]+$/'],
            'base_url' => ['nullable', 'string', 'max:255'],
            'session' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'language' => ['nullable', 'string', 'max:10', 'regex:/^[a-z]{2}(_[A-Z]{2})?$/'],
            'staff_template' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
        ], [
            'provider.in' => 'Penyedia WhatsApp tidak dikenal.',
            'token.regex' => 'Token hanya boleh berisi karakter yang tercetak tanpa spasi.',
            'phone_number_id.regex' => 'ID nomor telepon hanya angka.',
            'session.regex' => 'Nama sesi hanya huruf, angka, strip, dan garis bawah.',
            'language.regex' => 'Kode bahasa berbentuk id atau en_US.',
            'staff_template.regex' => 'Nama templat hanya huruf kecil, angka, dan garis bawah.',
        ]);

        $this->admin->save(
            $this->actorId($request), (string) $data['provider'], $data['phone_number_id'] ?? null, $data['token'] ?? null,
            $data['base_url'] ?? null, $data['session'] ?? null, (string) ($data['language'] ?? 'id'), $data['staff_template'] ?? null,
        );

        return back()->with('success', 'Pengaturan WhatsApp disimpan.');
    }

    public function test(Request $request): RedirectResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:30']], ['phone.required' => 'Isi nomor tujuan pesan uji.']);
        $result = $this->admin->sendTest($this->actorId($request), $data['phone']);

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}

<?php

declare(strict_types=1);

namespace Src\Billing\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Billing\Application\CashClosingService;
use Src\Shared\Application\RequiredFields;

/**
 * KSR-08: tutup kas harian. Izin cash.close; kasir hanya menutup dan melihat kas miliknya sendiri.
 */
final class CashClosingController
{
    public function index(Request $request, CashClosingService $closings): Response
    {
        $actor = $this->actorId($request);

        return Inertia::render('CashClosings/Index', [
            'history' => $closings->history($actor),
            'preview' => $closings->preview($actor),
        ]);
    }

    public function create(Request $request, CashClosingService $closings): Response
    {
        return Inertia::render('CashClosings/Form', [
            'required' => RequiredFields::from(self::rules()),
            'preview' => $closings->preview($this->actorId($request)),
        ]);
    }

    public function store(Request $request, CashClosingService $closings): RedirectResponse
    {
        $data = $request->validate(self::rules(), [
            'counted_amount.required' => 'Uang fisik wajib diisi.',
            'counted_amount.integer' => 'Uang fisik harus bilangan bulat rupiah.',
            'counted_amount.min' => 'Uang fisik tidak boleh negatif.',
        ]);
        $result = $closings->close($this->actorId($request), (int) $data['counted_amount'], $data['note'] ?? null);

        return to_route('cash-closings.index')->with('success', $result['difference_amount'] === 0 ? 'Kas ditutup, tidak ada selisih.' : 'Kas ditutup dengan selisih; tercatat di riwayat.');
    }

    /**
     * @return array<string, list<string>>
     */
    private static function rules(): array
    {
        return [
            'counted_amount' => ['required', 'integer', 'min:0', 'max:100000000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}

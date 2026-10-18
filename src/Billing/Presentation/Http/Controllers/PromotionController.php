<?php

declare(strict_types=1);

namespace Src\Billing\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Billing\Application\Loyalty\LoyaltyQueries;
use Src\Billing\Application\Loyalty\PromotionService;

/**
 * P3-06: voucher, penukaran poin, dan sesi paket pada tagihan. Melihat: invoice.view; mengubah: invoice.edit (dipasang di route).
 */
final class PromotionController
{
    public function __construct(private readonly PromotionService $promotions, private readonly LoyaltyQueries $queries) {}

    public function show(Request $request, string $invoice): Response
    {
        return Inertia::render('Invoices/Promotions', [...$this->queries->invoicePanel($invoice), 'can' => ['edit' => (bool) $request->user()?->can('invoice.edit')]]);
    }

    public function voucher(Request $request, string $invoice): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:30']], ['code.required' => 'Kode promo wajib diisi.']);
        $this->promotions->applyVoucher($this->actorId($request), $invoice, $data['code']);

        return back()->with('success', 'Kode promo dipakai pada tagihan.');
    }

    public function points(Request $request, string $invoice): RedirectResponse
    {
        $data = $request->validate(['points' => ['required', 'integer', 'min:1', 'max:1000000']], ['points.required' => 'Jumlah poin wajib diisi.', 'points.integer' => 'Jumlah poin berupa bilangan bulat.']);
        $this->promotions->redeemPoints($this->actorId($request), $invoice, (int) $data['points']);

        return back()->with('success', 'Poin ditukar menjadi diskon tagihan.');
    }

    public function remove(Request $request, string $invoice): RedirectResponse
    {
        $this->promotions->remove($this->actorId($request), $invoice);

        return back()->with('success', 'Promo dilepas dari tagihan.');
    }

    public function package(Request $request, string $invoice): RedirectResponse
    {
        $data = $request->validate(
            ['item_id' => ['required', 'string', 'size:26'], 'patient_package_id' => ['required', 'string', 'size:26']],
            ['item_id.required' => 'Pilih item tagihan.', 'patient_package_id.required' => 'Pilih paket pasien.'],
        );
        $this->promotions->applyPackage($this->actorId($request), $invoice, $data['item_id'], $data['patient_package_id']);

        return back()->with('success', 'Satu sesi paket dipakai untuk item ini.');
    }

    public function removePackage(Request $request, string $invoice): RedirectResponse
    {
        $data = $request->validate(['item_id' => ['required', 'string', 'size:26']]);
        $this->promotions->removePackage($this->actorId($request), $invoice, $data['item_id']);

        return back()->with('success', 'Sesi paket dikembalikan.');
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}

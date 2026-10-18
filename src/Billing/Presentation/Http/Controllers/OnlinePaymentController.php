<?php

declare(strict_types=1);

namespace Src\Billing\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Billing\Application\Online\OnlinePaymentAdministration;
use Src\Billing\Application\Online\OnlinePaymentQueries;
use Src\Billing\Application\Online\OnlinePayments;

/**
 * P3-05: QRIS dinamis untuk tagihan (payment.create), daftar rekonsiliasi (invoice.view), dan pengaturan (setting.manage);
 * izin dipasang di route. Webhook penyedia ada di WebhookController tanpa sesi.
 */
final class OnlinePaymentController
{
    public function __construct(private readonly OnlinePayments $online, private readonly OnlinePaymentQueries $queries, private readonly OnlinePaymentAdministration $admin) {}

    public function show(string $invoice): Response
    {
        return Inertia::render('Invoices/OnlinePayment', $this->queries->panel($invoice));
    }

    public function start(Request $request, string $invoice): RedirectResponse
    {
        $data = $request->validate(['amount' => ['nullable', 'integer', 'min:1', 'max:100000000000']], ['amount.integer' => 'Jumlah harus bilangan bulat rupiah.', 'amount.min' => 'Jumlah harus lebih dari nol.']);
        $this->online->start($this->actorId($request), $invoice, isset($data['amount']) ? (int) $data['amount'] : null);

        return to_route('invoices.online-payments', ['invoice' => $invoice])->with('success', 'Pembayaran QRIS dibuat. Buka halaman pembayaran agar pasien dapat memindai QR-nya.');
    }

    public function cancel(Request $request, string $invoice, string $intent): RedirectResponse
    {
        $this->online->cancel($this->actorId($request), $intent);

        return to_route('invoices.online-payments', ['invoice' => $invoice])->with('success', 'QR pembayaran dibatalkan.');
    }

    public function status(string $invoice, string $intent): JsonResponse
    {
        return response()->json(['data' => $this->queries->status($invoice, $intent), 'meta' => []])->header('Cache-Control', 'no-store');
    }

    public function desk(): Response
    {
        return Inertia::render('Invoices/OnlinePayments', $this->queries->desk());
    }

    public function settings(): Response
    {
        return Inertia::render('Settings/OnlinePayments', ['settings' => $this->admin->settings()]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'environment' => ['required', 'in:sandbox,production'],
            'client_id' => ['nullable', 'string', 'max:100'],
            'secret_key' => ['nullable', 'string', 'max:200'],
            'payment_method_id' => ['nullable', 'string', 'size:26'],
            'expiry_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
        ], ['environment.in' => 'Lingkungan harus sandbox atau production.', 'expiry_minutes.required' => 'Masa berlaku QR wajib diisi.', 'expiry_minutes.integer' => 'Masa berlaku QR berupa menit bulat.']);
        $this->admin->save($this->actorId($request), (bool) $data['enabled'], $data['environment'], $data['client_id'] ?? null, $data['secret_key'] ?? null, $data['payment_method_id'] ?? null, (int) $data['expiry_minutes']);

        return back()->with('success', 'Pengaturan pembayaran online disimpan.');
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}

<?php

declare(strict_types=1);

namespace Src\Billing\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Src\Billing\Application\InvoiceReader;
use Src\Billing\Application\InvoiceService;
use Src\Billing\Domain\InvalidBillingData;
use Src\Billing\Domain\PaymentType;
use Src\Clinic\Contracts\PaymentMethodCatalog;
use Src\Shared\Application\RequiredFields;

/**
 * KSR-03, KSR-04, KSR-07: pembayaran, uang muka, refund, dan pembatalan tagihan. Izin per route: payment.create,
 * payment.refund, invoice.void. Pembayaran wajib membawa Idempotency-Key (header atau isian) agar kirim ganda tidak
 * mencatat dua kali (M7).
 */
final class PaymentController
{
    public function createForm(Request $request, InvoiceReader $reader, PaymentMethodCatalog $methods, string $invoice): Response|RedirectResponse
    {
        $detail = $reader->detail($invoice, 'invoices.payments.create');
        $status = $detail['invoice']['status'];
        if ($status === 'void' || $status === 'paid') {
            return to_route('invoices.show', ['invoice' => $invoice])->with('error', $status === 'void' ? 'Tagihan sudah dibatalkan.' : 'Tagihan sudah lunas.');
        }

        return Inertia::render('Invoices/PaymentForm', [
            'required' => RequiredFields::from(self::paymentRules()),
            'invoice' => $this->head($detail),
            'methods' => $this->methodOptions($methods),
            'allowPay' => $detail['invoice']['lifecycle'] === 'issued',
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request, InvoiceService $invoices, string $invoice): RedirectResponse
    {
        $data = $request->validate(self::paymentRules(), [
            'payment_method_id.required' => 'Pilih metode pembayaran.',
            'amount.required' => 'Jumlah wajib diisi.',
            'amount.integer' => 'Jumlah harus bilangan bulat rupiah.',
            'amount.min' => 'Jumlah harus lebih dari nol.',
            'type.required' => 'Pilih jenis pembayaran.',
        ]);
        $payment = $invoices->recordPayment(
            $this->actorId($request),
            $invoice,
            $data['payment_method_id'],
            PaymentType::from($data['type']),
            (int) $data['amount'],
            $this->idempotencyKey($request),
            $data['reference_no'] ?? null,
        );

        return to_route('invoices.show', ['invoice' => $invoice])->with('success', 'Pembayaran dicatat'.($payment->receiptNo === null ? '.' : ' (kwitansi '.$payment->receiptNo.').'));
    }

    public function refundForm(Request $request, InvoiceReader $reader, PaymentMethodCatalog $methods, string $invoice): Response|RedirectResponse
    {
        $detail = $reader->detail($invoice, 'invoices.refunds.create');
        if ((int) $detail['invoice']['paid_amount'] <= 0) {
            return to_route('invoices.show', ['invoice' => $invoice])->with('error', 'Belum ada pembayaran yang dapat dikembalikan.');
        }

        return Inertia::render('Invoices/RefundForm', [
            'required' => RequiredFields::from(self::refundRules()),
            'invoice' => $this->head($detail),
            'methods' => $this->methodOptions($methods),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function refund(Request $request, InvoiceService $invoices, string $invoice): RedirectResponse
    {
        $data = $request->validate(self::refundRules(), [
            'payment_method_id.required' => 'Pilih metode pengembalian.',
            'amount.required' => 'Jumlah wajib diisi.',
            'amount.integer' => 'Jumlah harus bilangan bulat rupiah.',
            'amount.min' => 'Jumlah harus lebih dari nol.',
            'reason.required' => 'Alasan pengembalian wajib diisi.',
        ]);
        $invoices->refund($this->actorId($request), $invoice, $data['payment_method_id'], (int) $data['amount'], $data['reason'], $this->idempotencyKey($request));

        return to_route('invoices.show', ['invoice' => $invoice])->with('success', 'Pengembalian dicatat.');
    }

    public function voidForm(Request $request, InvoiceReader $reader, string $invoice): Response|RedirectResponse
    {
        $detail = $reader->detail($invoice, 'invoices.void');
        if ($detail['invoice']['status'] === 'void') {
            return to_route('invoices.show', ['invoice' => $invoice])->with('error', 'Tagihan sudah dibatalkan.');
        }

        return Inertia::render('Invoices/VoidForm', [
            'required' => RequiredFields::from(self::voidRules()),
            'invoice' => $this->head($detail),
        ]);
    }

    public function void(Request $request, InvoiceService $invoices, string $invoice): RedirectResponse
    {
        $data = $request->validate(self::voidRules(), ['reason.required' => 'Alasan pembatalan wajib diisi.']);
        $invoices->void($this->actorId($request), $invoice, $data['reason']);

        return to_route('invoices.show', ['invoice' => $invoice])->with('success', 'Tagihan dibatalkan.');
    }

    public function summary(InvoiceReader $reader, string $invoice): JsonResponse
    {
        $i = $reader->detail($invoice, 'internal-api.invoices.summary')['invoice'];

        return response()->json(['data' => [
            'id' => $i['id'],
            'status' => $i['status'],
            'total_amount' => $i['total_amount'],
            'paid_amount' => $i['paid_amount'],
            'balance_amount' => $i['balance_amount'],
        ]]);
    }

    /**
     * @return array<string, list<string>>
     */
    private static function paymentRules(): array
    {
        return [
            'payment_method_id' => ['required', 'string', 'size:26'],
            'type' => ['required', 'in:pay,dp'],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'reference_no' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private static function refundRules(): array
    {
        return [
            'payment_method_id' => ['required', 'string', 'size:26'],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private static function voidRules(): array
    {
        return ['reason' => ['required', 'string', 'max:255']];
    }

    private function idempotencyKey(Request $request): string
    {
        $key = $request->header('Idempotency-Key') ?? $request->input('idempotency_key');
        if (! is_string($key) || preg_match('/^[A-Za-z0-9._:-]{8,100}$/', $key) !== 1) {
            throw new InvalidBillingData('payment.idempotency_key_required', 'Idempotency-Key wajib (8 sampai 100 karakter huruf, angka, atau . _ : -).');
        }

        return $key;
    }

    /**
     * @return list<array{value: string, label: string, type: string}>
     */
    private function methodOptions(PaymentMethodCatalog $methods): array
    {
        return array_map(fn ($m) => ['value' => $m->id, 'label' => $m->name, 'type' => $m->type], $methods->active());
    }

    /**
     * @param  array{invoice: array<string, mixed>, items: list<array<string, mixed>>, payments: list<array<string, mixed>>, installments: array<string, mixed>|null}  $detail
     * @return array<string, mixed>
     */
    private function head(array $detail): array
    {
        $i = $detail['invoice'];

        return [
            'id' => $i['id'],
            'number' => $i['number'],
            'lifecycle' => $i['lifecycle'],
            'status' => $i['status'],
            'patient_name' => $i['patient_name'],
            'medical_record_no' => $i['medical_record_no'],
            'total_amount' => $i['total_amount'],
            'paid_amount' => $i['paid_amount'],
            'balance_amount' => $i['balance_amount'],
        ];
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}

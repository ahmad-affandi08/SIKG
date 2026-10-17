<?php

declare(strict_types=1);

namespace Src\Billing\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Billing\Application\InstallmentService;
use Src\Billing\Application\InvoiceReader;
use Src\Shared\Application\RequiredFields;

/**
 * KSR-05: jadwal cicilan sisa tagihan. Izin payment.create.
 */
final class InstallmentController
{
    public function create(Request $request, InvoiceReader $reader, string $invoice): Response|RedirectResponse
    {
        $detail = $reader->detail($invoice, 'invoices.payment-plan.create');
        $i = $detail['invoice'];
        if ($detail['installments'] !== null || $i['lifecycle'] !== 'issued' || (int) $i['balance_amount'] <= 0) {
            return to_route('invoices.show', ['invoice' => $invoice])->with('error', 'Jadwal cicilan tidak dapat dibuat untuk tagihan ini.');
        }

        return Inertia::render('Invoices/InstallmentForm', [
            'required' => RequiredFields::from(self::rules()),
            'invoice' => [
                'id' => $i['id'],
                'number' => $i['number'],
                'patient_name' => $i['patient_name'],
                'medical_record_no' => $i['medical_record_no'],
                'total_amount' => $i['total_amount'],
                'paid_amount' => $i['paid_amount'],
                'balance_amount' => $i['balance_amount'],
            ],
        ]);
    }

    public function store(Request $request, InstallmentService $installments, string $invoice): RedirectResponse
    {
        $data = $request->validate(self::rules(), [
            'count.required' => 'Jumlah cicilan wajib diisi.',
            'count.between' => 'Jumlah cicilan harus antara 2 dan 36.',
            'first_due_date.required' => 'Tanggal jatuh tempo pertama wajib diisi.',
            'first_due_date.date_format' => 'Tanggal jatuh tempo tidak valid.',
        ]);
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);
        $installments->create($id, $invoice, (int) $data['count'], $data['first_due_date']);

        return to_route('invoices.show', ['invoice' => $invoice])->with('success', 'Jadwal cicilan dibuat.');
    }

    /**
     * @return array<string, list<string>>
     */
    private static function rules(): array
    {
        return [
            'count' => ['required', 'integer', 'between:2,36'],
            'first_due_date' => ['required', 'date_format:Y-m-d'],
        ];
    }
}

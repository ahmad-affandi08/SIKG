<?php

declare(strict_types=1);

namespace Src\Billing\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Billing\Application\InvoiceListing;
use Src\Billing\Application\InvoiceReader;
use Src\Billing\Application\InvoiceService;
use Src\Billing\Domain\Discount;
use Src\Billing\Domain\DiscountKind;
use Src\Billing\Domain\InvalidBillingData;
use Src\Billing\Domain\InvoiceStatus;
use Src\Clinic\Contracts\DiscountLimits;
use Src\Shared\Application\RequiredFields;

/**
 * KSR-01, KSR-02: daftar, detail, item manual, dan diskon tagihan. Izin dipasang per route: invoice.view, invoice.edit,
 * invoice.discount.
 */
final class InvoiceController
{
    private const PER_PAGE = 25;

    public function index(Request $request, InvoiceListing $listing): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:'.implode(',', array_map(fn (InvoiceStatus $s) => $s->value, InvoiceStatus::cases()))],
            'number' => ['nullable', 'string', 'max:40'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return Inertia::render('Invoices/Index', [
            ...$listing->list($filters, (int) ($filters['page'] ?? 1), self::PER_PAGE),
            'filters' => ['status' => $filters['status'] ?? '', 'number' => $filters['number'] ?? '', 'from' => $filters['from'] ?? '', 'to' => $filters['to'] ?? ''],
            'statuses' => array_map(fn (InvoiceStatus $s) => ['value' => $s->value, 'label' => $s->label()], InvoiceStatus::cases()),
        ]);
    }

    public function show(Request $request, InvoiceReader $reader, string $invoice): Response
    {
        $user = $request->user();

        return Inertia::render('Invoices/Show', [
            ...$reader->detail($invoice),
            'can' => [
                'edit' => (bool) $user?->can('invoice.edit'),
                'discount' => (bool) $user?->can('invoice.discount'),
                'pay' => (bool) $user?->can('payment.create'),
                'refund' => (bool) $user?->can('payment.refund'),
                'void' => (bool) $user?->can('invoice.void'),
            ],
        ]);
    }

    public function issue(Request $request, InvoiceService $invoices, string $invoice): RedirectResponse
    {
        $invoices->issue($this->actorId($request), $invoice);

        return to_route('invoices.show', ['invoice' => $invoice])->with('success', 'Tagihan diterbitkan.');
    }

    // --- Item manual ---

    public function itemForm(Request $request, InvoiceReader $reader, string $invoice): Response|RedirectResponse
    {
        $detail = $reader->detail($invoice, 'invoices.items.create');
        if (! $detail['invoice']['editable']) {
            return to_route('invoices.show', ['invoice' => $invoice])->with('error', 'Tagihan tidak dapat diubah lagi.');
        }

        return Inertia::render('Invoices/ItemForm', [
            'required' => RequiredFields::from(self::itemRules()),
            'invoice' => $this->head($detail),
            'item' => null,
        ]);
    }

    public function storeItem(Request $request, InvoiceService $invoices, string $invoice): RedirectResponse
    {
        $data = $request->validate(self::itemRules(), self::itemMessages());
        $invoices->addManualItem($this->actorId($request), $invoice, $data['description'], (int) $data['quantity'], (int) $data['unit_price_amount']);

        return to_route('invoices.show', ['invoice' => $invoice])->with('success', 'Item ditambahkan.');
    }

    public function editItem(Request $request, InvoiceReader $reader, string $invoice, string $item): Response|RedirectResponse
    {
        $detail = $reader->detail($invoice, 'invoices.items.edit');
        $row = collect($detail['items'])->firstWhere('id', $item);
        abort_if($row === null, 404);
        if (! $detail['invoice']['editable'] || ! $row['manual']) {
            return to_route('invoices.show', ['invoice' => $invoice])->with('error', 'Item ini tidak dapat diubah.');
        }

        return Inertia::render('Invoices/ItemForm', [
            'required' => RequiredFields::from(['quantity' => self::itemRules()['quantity']]),
            'invoice' => $this->head($detail),
            'item' => $row,
        ]);
    }

    public function updateItem(Request $request, InvoiceService $invoices, string $invoice, string $item): RedirectResponse
    {
        $data = $request->validate(['quantity' => self::itemRules()['quantity']], self::itemMessages());
        $invoices->changeQuantity($this->actorId($request), $invoice, $item, (int) $data['quantity']);

        return to_route('invoices.show', ['invoice' => $invoice])->with('success', 'Jumlah item diperbarui.');
    }

    public function removeItem(Request $request, InvoiceService $invoices, string $invoice, string $item): RedirectResponse
    {
        $invoices->removeItem($this->actorId($request), $invoice, $item);

        return to_route('invoices.show', ['invoice' => $invoice])->with('success', 'Item dihapus dari tagihan.');
    }

    // --- Diskon ---

    public function discountForm(Request $request, InvoiceReader $reader, DiscountLimits $limits, string $invoice, ?string $item = null): Response|RedirectResponse
    {
        $detail = $reader->detail($invoice, 'invoices.discount');
        $row = $item === null ? null : collect($detail['items'])->firstWhere('id', $item);
        abort_if($item !== null && $row === null, 404);
        if (! $detail['invoice']['editable']) {
            return to_route('invoices.show', ['invoice' => $invoice])->with('error', 'Tagihan tidak dapat diubah lagi.');
        }

        return Inertia::render('Invoices/DiscountForm', [
            'required' => RequiredFields::from(self::discountRules()),
            'invoice' => $this->head($detail),
            'item' => $row,
            'current' => $row ?? $detail['invoice'],
            'limitPercent' => intdiv($limits->basisPointsFor($this->roles($request)), 100),
        ]);
    }

    public function storeDiscount(Request $request, InvoiceService $invoices, DiscountLimits $limits, string $invoice, ?string $item = null): RedirectResponse
    {
        $data = $request->validate(self::discountRules(), [
            'kind.required' => 'Pilih jenis diskon.',
            'value.required_unless' => 'Nilai diskon wajib diisi.',
            'reason.required_unless' => 'Alasan diskon wajib diisi.',
        ]);
        $discount = $this->discount($data);
        $limit = $limits->basisPointsFor($this->roles($request));

        if ($item === null) {
            $invoices->applyInvoiceDiscount($this->actorId($request), $invoice, $discount, $limit);
        } else {
            $invoices->applyItemDiscount($this->actorId($request), $invoice, $item, $discount, $limit);
        }

        return to_route('invoices.show', ['invoice' => $invoice])->with('success', $discount === null ? 'Diskon dihapus.' : 'Diskon diterapkan.');
    }

    /**
     * @return array<string, list<string>>
     */
    private static function itemRules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'between:1,9999'],
            'unit_price_amount' => ['required', 'integer', 'between:0,1000000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function itemMessages(): array
    {
        return [
            'description.required' => 'Uraian item wajib diisi.',
            'quantity.required' => 'Jumlah wajib diisi.',
            'quantity.between' => 'Jumlah harus antara 1 dan 9999.',
            'unit_price_amount.required' => 'Harga satuan wajib diisi.',
            'unit_price_amount.integer' => 'Harga satuan harus bilangan bulat rupiah.',
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private static function discountRules(): array
    {
        return [
            'kind' => ['required', 'in:percent,amount,none'],
            'value' => ['required_unless:kind,none', 'nullable', 'string', 'max:12'],
            'reason' => ['required_unless:kind,none', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function discount(array $data): ?Discount
    {
        if ($data['kind'] === 'none') {
            return null;
        }
        $value = trim((string) $data['value']);
        $reason = (string) ($data['reason'] ?? '');
        if ($data['kind'] === 'percent') {
            if (preg_match('/^(\d{1,3})(?:[.,](\d{1,2}))?$/', $value, $m) !== 1) {
                throw new InvalidBillingData('discount.invalid_percent', 'Diskon persen berformat angka, contoh 10 atau 12,5.');
            }

            return new Discount(DiscountKind::Percent, (int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0'), $reason);
        }
        if (preg_match('/^\d{1,12}$/', $value) !== 1) {
            throw new InvalidBillingData('discount.invalid_amount', 'Diskon nominal berupa bilangan bulat rupiah.');
        }

        return new Discount(DiscountKind::Amount, (int) $value, $reason);
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
            'patient_name' => $i['patient_name'],
            'medical_record_no' => $i['medical_record_no'],
            'subtotal_amount' => $i['subtotal_amount'],
            'total_amount' => $i['total_amount'],
        ];
    }

    /**
     * @return list<string>
     */
    private function roles(Request $request): array
    {
        $user = $request->user();

        return $user === null ? [] : array_values(array_map('strval', $user->getRoleNames()->all()));
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}

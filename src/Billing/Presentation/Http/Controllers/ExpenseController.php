<?php

declare(strict_types=1);

namespace Src\Billing\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Billing\Application\ExpenseQueries;
use Src\Billing\Application\ExpenseService;
use Src\Shared\Application\RequiredFields;

/**
 * P2-08: biaya operasional. Melihat butuh expense.view (laporan keuangan penuh); mencatat dan membatalkan butuh expense.manage.
 */
final class ExpenseController
{
    public function __construct(private readonly ExpenseService $service, private readonly ExpenseQueries $queries) {}

    public function index(Request $request): Response
    {
        $query = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'category' => ['nullable', 'string', 'size:26'],
        ]);

        $data = $this->queries->list($query['from'] ?? null, $query['to'] ?? null, $query['category'] ?? null);

        return Inertia::render('Expenses/Index', [
            ...$data,
            'filters' => ['from' => $data['range']['from'], 'to' => $data['range']['to'], 'category' => $query['category'] ?? ''],
            'can' => ['manage' => (bool) $request->user()?->can('expense.manage')],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Expenses/Create', [...$this->queries->formData(), 'required' => RequiredFields::from(self::rules())]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(self::rules(), [
            'expense_date.required' => 'Tanggal biaya wajib diisi.',
            'category_id.required' => 'Kategori biaya wajib dipilih.',
            'amount.required' => 'Jumlah biaya wajib diisi.',
            'amount.integer' => 'Jumlah biaya berupa bilangan bulat rupiah.',
            'amount.min' => 'Jumlah biaya harus lebih dari nol.',
            'description.required' => 'Keterangan biaya wajib diisi.',
        ]);

        $this->service->record($this->actorId($request), $data['expense_date'], $data['category_id'], (int) $data['amount'], $data['payee'] ?? null, $data['description'], $data['reference_no'] ?? null);

        return to_route('expenses.index')->with('success', 'Biaya operasional dicatat.');
    }

    public function void(Request $request, string $expense): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], ['reason.required' => 'Alasan pembatalan wajib diisi.']);
        $this->service->void($this->actorId($request), $expense, $data['reason']);

        return back()->with('success', 'Biaya dibatalkan dan tidak lagi masuk laporan.');
    }

    /**
     * @return array<string, list<string>>
     */
    private static function rules(): array
    {
        return [
            'expense_date' => ['required', 'date_format:Y-m-d'],
            'category_id' => ['required', 'string', 'size:26'],
            'amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'payee' => ['nullable', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:255'],
            'reference_no' => ['nullable', 'string', 'max:60'],
        ];
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}

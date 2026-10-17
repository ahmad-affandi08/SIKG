<?php

declare(strict_types=1);

namespace Src\Pharmacy\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Pharmacy\Application\ProcedureMaterialService;
use Src\Shared\Application\RequiredFields;

/**
 * P2-05: bahan habis pakai standar per tindakan. Seluruh route memakai izin stock.manage.
 */
final class ProcedureMaterialController
{
    public function __construct(private readonly ProcedureMaterialService $materials) {}

    public function index(): Response
    {
        return Inertia::render('Settings/ProcedureMaterials', [...$this->materials->overview(), 'required' => RequiredFields::from(self::rules())]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(self::rules(), ['procedure_id.required' => 'Tindakan wajib dipilih.', 'medicine_id.required' => 'Obat atau bahan wajib dipilih.', 'quantity.required' => 'Jumlah wajib diisi.']);
        $this->materials->set($this->actorId($request), $data['procedure_id'], $data['medicine_id'], (int) $data['quantity']);

        return back()->with('success', 'Bahan tindakan disimpan.');
    }

    public function remove(Request $request, string $material): RedirectResponse
    {
        $this->materials->remove($this->actorId($request), $material);

        return back()->with('success', 'Bahan dilepas dari tindakan.');
    }

    /**
     * @return array<string, list<string>>
     */
    private static function rules(): array
    {
        return [
            'procedure_id' => ['required', 'string', 'size:26'],
            'medicine_id' => ['required', 'string', 'size:26'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}

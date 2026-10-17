<?php

declare(strict_types=1);

namespace Src\Clinical\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Src\Clinical\Application\TreatmentPlans;
use Src\Clinical\Domain\PlanItemRecord;
use Src\Clinical\Domain\PlanItemStatus;
use Src\Clinical\Domain\TreatmentPlanRecord;
use Src\Shared\Application\RequiredFields;

/**
 * RM-06: rencana perawatan multi-kunjungan. Melihat butuh encounter.view; membuat dan mengubah butuh treatment_plan.manage
 * (Dokter). Formulir berupa halaman sendiri, bukan modal.
 */
final class TreatmentPlanController
{
    public function index(Request $request, string $patient, TreatmentPlans $plans): Response
    {
        $data = $plans->overview($patient, 'treatment-plans.index');

        return Inertia::render('TreatmentPlans/Index', [
            'patient' => $data['patient'],
            'plans' => array_map(fn (TreatmentPlanRecord $p) => $this->present($p), $data['plans']),
            'can' => ['manage' => (bool) $request->user()?->can('treatment_plan.manage')],
            'statuses' => array_map(fn (PlanItemStatus $s) => ['value' => $s->value, 'label' => $s->label()], PlanItemStatus::cases()),
        ]);
    }

    public function createForm(string $patient, TreatmentPlans $plans): Response
    {
        return Inertia::render('TreatmentPlans/PlanForm', [
            'required' => RequiredFields::from(self::planRules()),
            'patient' => $plans->overview($patient, 'treatment-plans.create')['patient'],
        ]);
    }

    public function store(Request $request, string $patient, TreatmentPlans $plans): RedirectResponse
    {
        $data = $request->validate(self::planRules(), ['title.required' => 'Judul rencana wajib diisi.']);
        $plans->create($this->actorId($request), $patient, $data['title'], $data['notes'] ?? null);

        return to_route('treatment-plans.index', ['patient' => $patient])->with('success', 'Rencana perawatan dibuat. Tambahkan langkah-langkahnya.');
    }

    public function itemForm(string $plan, TreatmentPlans $plans): Response
    {
        $detail = $plans->detail($plan);

        return Inertia::render('TreatmentPlans/ItemForm', [
            'required' => RequiredFields::from(self::itemRules()),
            'patient' => $detail['patient'],
            'plan' => ['id' => $detail['plan']->id, 'title' => $detail['plan']->title],
        ]);
    }

    public function storeItem(Request $request, string $plan, TreatmentPlans $plans): RedirectResponse
    {
        $data = $request->validate(self::itemRules(), ['procedure_id.required' => 'Tindakan wajib dipilih.', 'quantity.required' => 'Jumlah wajib diisi.']);
        $plans->addItem($this->actorId($request), $plan, $data['procedure_id'], $data['tooth_fdi'] ?? null, (int) $data['quantity'], $data['notes'] ?? null);
        $patient = $plans->detail($plan)['patient']['id'];

        return to_route('treatment-plans.index', ['patient' => $patient])->with('success', 'Langkah ditambahkan ke rencana.');
    }

    public function changeStatus(Request $request, string $plan, string $item, TreatmentPlans $plans): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::enum(PlanItemStatus::class)]], ['status.required' => 'Status wajib dipilih.']);
        $plans->changeItemStatus($this->actorId($request), $plan, $item, PlanItemStatus::from($data['status']));

        return back()->with('success', 'Status langkah diperbarui.');
    }

    public function removeItem(Request $request, string $plan, string $item, TreatmentPlans $plans): RedirectResponse
    {
        $plans->removeItem($this->actorId($request), $plan, $item);

        return back()->with('success', 'Langkah dihapus dari rencana.');
    }

    public function cancel(Request $request, string $plan, TreatmentPlans $plans): RedirectResponse
    {
        $plans->cancel($this->actorId($request), $plan);

        return back()->with('success', 'Rencana perawatan dibatalkan.');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(TreatmentPlanRecord $plan): array
    {
        return [
            'id' => $plan->id,
            'title' => $plan->title,
            'notes' => $plan->notes,
            'status' => $plan->status->value,
            'status_label' => $plan->status->label(),
            'created_at' => $plan->createdAt->format('c'),
            'estimate_amount' => $plan->estimateAmount(),
            'remaining_amount' => $plan->remainingAmount(),
            'items' => array_map(fn (PlanItemRecord $i) => [
                'id' => $i->id,
                'sequence' => $i->sequence,
                'code' => $i->procedureCode,
                'name' => $i->procedureName,
                'tooth_fdi' => $i->toothFdi,
                'quantity' => $i->quantity,
                'unit_price_amount' => $i->unitPriceAmount,
                'estimate_amount' => $i->estimateAmount,
                'status' => $i->status->value,
                'status_label' => $i->status->label(),
                'notes' => $i->notes,
                'encounter_id' => $i->encounterId,
                'completed_at' => $i->completedAt?->format('c'),
            ], $plan->items),
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private static function planRules(): array
    {
        return ['title' => ['required', 'string', 'max:150'], 'notes' => ['nullable', 'string', 'max:1000']];
    }

    /**
     * @return array<string, list<string>>
     */
    private static function itemRules(): array
    {
        return [
            'procedure_id' => ['required', 'string', 'size:26'],
            'tooth_fdi' => ['nullable', 'integer'],
            'quantity' => ['required', 'integer', 'between:1,'.TreatmentPlans::MAX_QUANTITY],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}

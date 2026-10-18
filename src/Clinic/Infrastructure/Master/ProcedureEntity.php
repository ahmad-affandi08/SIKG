<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure\Master;

use Illuminate\Validation\Rule;
use Src\Clinic\Domain\ProcedureScope;
use Src\Clinic\Infrastructure\Eloquent\ProcedureModel;
use Src\Clinic\Infrastructure\Eloquent\ToothConditionModel;
use Src\Shared\Application\Master\ImportableMaster;
use Src\Shared\Application\Master\MasterField;
use Src\Shared\Application\TenantContext;
use Src\Shared\Infrastructure\Master\EloquentMasterEntity;

/**
 * MST-04: katalog tindakan (kode, nama, kategori, tarif standar, cakupan). Tarif standar hanya sumber salinan;
 * item tagihan dan tindakan menyalin tarif saat dibuat sehingga perubahan tarif tidak mengubah data lama (MST-08, M7).
 */
final class ProcedureEntity extends EloquentMasterEntity implements ImportableMaster
{
    public function importKey(): string
    {
        return 'code';
    }

    public function key(): string
    {
        return 'procedures';
    }

    public function title(): string
    {
        return 'Katalog tindakan dan tarif';
    }

    public function viewPermission(): string
    {
        return 'tariff.view';
    }

    public function managePermission(): string
    {
        return 'tariff.manage';
    }

    public function fields(): array
    {
        return [
            new MasterField('code', 'Kode', 'text', required: true, max: 30),
            new MasterField('name', 'Nama tindakan', 'text', required: true, max: 255),
            new MasterField('category', 'Kategori', 'text', max: 100),
            new MasterField('scope', 'Cakupan', 'select', required: true, options: array_map(
                fn (ProcedureScope $s) => ['value' => $s->value, 'label' => $s->label()],
                ProcedureScope::cases(),
            )),
            new MasterField('default_price_amount', 'Tarif standar (Rp)', 'money', required: true, help: 'Rupiah bulat. Mengubah tarif tidak mengubah tagihan yang sudah dibuat.'),
            new MasterField('result_condition_code', 'Kondisi gigi hasil tindakan', 'select', options: $this->conditionOptions(), help: 'Bila diisi, dokter ditawari memperbarui odontogram setelah tindakan dicatat (contoh: tambal menjadi Tambalan). Kosong berarti odontogram tidak berubah.'),
            new MasterField('icd9_code', 'Kode ICD-9-CM (SATUSEHAT)', 'text', max: 10, help: 'Kode prosedur ICD-9-CM untuk pelaporan ke SATUSEHAT, contoh 23.2. Tindakan tanpa kode tidak ikut dikirim sebagai prosedur.'),
        ];
    }

    public function columns(): array
    {
        return ['code', 'name', 'category', 'scope', 'default_price_amount', 'result_condition_code', 'icd9_code'];
    }

    public function rules(?string $ignoreId): array
    {
        $clinicId = app(TenantContext::class)->clinicId();

        return [
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('procedures', 'code')->where('clinic_id', $clinicId)->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'scope' => ['required', Rule::enum(ProcedureScope::class)],
            'default_price_amount' => ['required', 'integer', 'min:0', 'max:9999999999'],
            'icd9_code' => ['nullable', 'string', 'max:10', 'regex:/^\d{1,2}(\.\d{1,2})?$/'],
            'result_condition_code' => ['nullable', 'string', Rule::exists('tooth_conditions', 'code')->where('clinic_id', $clinicId)->where('is_active', true)],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function conditionOptions(): array
    {
        $options = [];
        foreach (ToothConditionModel::query()->where('is_active', true)->where('code', '!=', 'SEH')->orderBy('name')->get() as $c) {
            $options[] = ['value' => $c->code, 'label' => $c->name];
        }

        return $options;
    }

    protected function modelClass(): string
    {
        return ProcedureModel::class;
    }

    protected function subject(): string
    {
        return 'procedure';
    }

    protected function orderColumn(): string
    {
        return 'code';
    }
}

<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure\Master;

use Closure;
use Illuminate\Validation\Rule;
use Src\Clinic\Contracts\DocumentPlaceholders;
use Src\Clinic\Infrastructure\Eloquent\DocumentTemplateModel;
use Src\Clinic\Infrastructure\Eloquent\ProcedureModel;
use Src\Shared\Application\Master\MasterField;
use Src\Shared\Application\TenantContext;
use Src\Shared\Infrastructure\Master\EloquentMasterEntity;

/**
 * RM-09 dan RM-10: templat informed consent (per jenis tindakan), surat keterangan, dan surat rujukan. Teks memakai penanda
 * `{{nama}}` yang diisi saat dokumen dibuat; penanda yang tidak dikenal ditolak.
 */
final class DocumentTemplateEntity extends EloquentMasterEntity
{
    private const TYPES = [
        ['value' => 'consent', 'label' => 'Informed consent'],
        ['value' => 'certificate', 'label' => 'Surat keterangan'],
        ['value' => 'referral', 'label' => 'Surat rujukan'],
    ];

    public function key(): string
    {
        return 'document-templates';
    }

    public function title(): string
    {
        return 'Templat dokumen klinis';
    }

    public function viewPermission(): string
    {
        return 'setting.manage';
    }

    public function managePermission(): string
    {
        return 'setting.manage';
    }

    public function fields(): array
    {
        $placeholders = implode(', ', array_map(fn (string $name) => '{{'.$name.'}}', array_keys(DocumentPlaceholders::ALL)));

        return [
            new MasterField('type', 'Jenis dokumen', 'select', required: true, options: self::TYPES),
            new MasterField('name', 'Nama templat', 'text', required: true, max: 150),
            new MasterField('procedure_id', 'Untuk tindakan (informed consent)', 'select', options: $this->procedureOptions(), help: 'Hanya untuk informed consent: templat ini ditawarkan lebih dulu saat tindakan itu dipilih. Kosong berarti berlaku umum.'),
            new MasterField('body', 'Isi templat', 'textarea', required: true, max: 8000, help: 'Penanda yang dapat dipakai: '.$placeholders.'. Teks dapat disunting lagi sebelum dokumen dicetak.'),
        ];
    }

    public function columns(): array
    {
        return ['type', 'name', 'procedure_id'];
    }

    public function rules(?string $ignoreId): array
    {
        return [
            'type' => ['required', Rule::in(array_column(self::TYPES, 'value'))],
            'name' => ['required', 'string', 'max:150'],
            'procedure_id' => ['nullable', 'string', 'size:26', Rule::exists('procedures', 'id')->where('clinic_id', $this->clinicId())->whereNull('deleted_at')],
            'body' => ['required', 'string', 'max:8000', function (string $attribute, mixed $value, Closure $fail): void {
                $unknown = DocumentPlaceholders::unknownIn((string) $value);
                if ($unknown !== []) {
                    $fail('Penanda tidak dikenal: '.implode(', ', array_map(fn (string $n) => '{{'.$n.'}}', $unknown)).'.');
                }
            }],
        ];
    }

    protected function modelClass(): string
    {
        return DocumentTemplateModel::class;
    }

    protected function subject(): string
    {
        return 'document_template';
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function procedureOptions(): array
    {
        $options = [];
        foreach (ProcedureModel::query()->where('is_active', true)->orderBy('code')->get() as $p) {
            $options[] = ['value' => $p->id, 'label' => $p->code.' - '.$p->name];
        }

        return $options;
    }

    private function clinicId(): ?string
    {
        return app(TenantContext::class)->clinicId();
    }
}

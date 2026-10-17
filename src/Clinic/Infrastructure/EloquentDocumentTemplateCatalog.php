<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure;

use Src\Clinic\Contracts\DocumentTemplate;
use Src\Clinic\Contracts\DocumentTemplateCatalog;
use Src\Clinic\Infrastructure\Eloquent\DocumentTemplateModel;

final class EloquentDocumentTemplateCatalog implements DocumentTemplateCatalog
{
    public function active(string $type): array
    {
        return array_values(DocumentTemplateModel::query()->where('type', $type)->where('is_active', true)->orderBy('name')->get()
            ->map(fn (DocumentTemplateModel $t) => $this->snapshot($t))->all());
    }

    public function find(string $id): ?DocumentTemplate
    {
        $template = DocumentTemplateModel::query()->where('is_active', true)->find($id);

        return $template instanceof DocumentTemplateModel ? $this->snapshot($template) : null;
    }

    private function snapshot(DocumentTemplateModel $t): DocumentTemplate
    {
        /** @var 'consent'|'certificate'|'referral' $type */
        $type = $t->type;

        return new DocumentTemplate($t->id, $type, $t->name, $t->procedure_id, $t->body);
    }
}

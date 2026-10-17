<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure\Eloquent;

use Src\Shared\Infrastructure\Eloquent\TenantMasterModel;

/**
 * @property string $type
 * @property string $name
 * @property string|null $procedure_id
 * @property string $body
 */
final class DocumentTemplateModel extends TenantMasterModel
{
    protected $table = 'document_templates';

    /** @var list<string> */
    protected $fillable = ['type', 'name', 'procedure_id', 'body', 'branch_id'];
}

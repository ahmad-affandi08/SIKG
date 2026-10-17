<?php

declare(strict_types=1);

namespace Src\Insurance\Infrastructure\Eloquent;

use Src\Shared\Infrastructure\Eloquent\TenantMasterModel;

/**
 * @property string $name
 * @property string $type
 * @property string|null $contact_name
 * @property string|null $contact_phone
 * @property string|null $contact_email
 * @property string|null $notes
 */
final class InsurerModel extends TenantMasterModel
{
    protected $table = 'insurers';

    /** @var list<string> */
    protected $fillable = ['name', 'type', 'contact_name', 'contact_phone', 'contact_email', 'notes', 'branch_id'];
}

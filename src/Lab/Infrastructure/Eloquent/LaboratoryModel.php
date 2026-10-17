<?php

declare(strict_types=1);

namespace Src\Lab\Infrastructure\Eloquent;

use Src\Shared\Infrastructure\Eloquent\TenantMasterModel;

/**
 * P2-06: laboratorium gigi rekanan. Kontak adalah kontak perusahaan lab, bukan data pasien.
 *
 * @property string $name
 */
final class LaboratoryModel extends TenantMasterModel
{
    protected $table = 'laboratories';

    /** @var list<string> */
    protected $fillable = ['name', 'contact_name', 'phone', 'email', 'address', 'notes', 'branch_id'];
}

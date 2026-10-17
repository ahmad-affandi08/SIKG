<?php

declare(strict_types=1);

namespace Src\Pharmacy\Infrastructure\Eloquent;

use Src\Shared\Infrastructure\Eloquent\TenantMasterModel;

/**
 * P2-05: pemasok obat dan bahan. Kontak adalah kontak perusahaan pemasok, bukan data pasien.
 *
 * @property string $name
 */
final class SupplierModel extends TenantMasterModel
{
    protected $table = 'suppliers';

    /** @var list<string> */
    protected $fillable = ['name', 'contact_name', 'phone', 'email', 'address', 'notes', 'branch_id'];
}

<?php

declare(strict_types=1);

namespace Src\Clinical\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;
use Src\Shared\Infrastructure\Eloquent\UsesUlid;
use Src\Shared\Infrastructure\Tenancy\BelongsToClinic;

/**
 * Bukti tanda tangan dokumen bersifat final: tidak diubah dan tidak dihapus.
 *
 * @property string $id
 * @property string $document_id
 * @property string $patient_id
 * @property string $signer_name
 * @property string $signer_role
 * @property string|null $witness_name
 * @property string $pdf_path
 * @property bool $pdf_encrypted
 * @property string $pdf_sha256
 * @property string $body_sha256
 * @property string $signed_by
 * @property Carbon $signed_at
 */
final class DocumentSignatureModel extends Model
{
    use BelongsToClinic;
    use UsesUlid;

    public const UPDATED_AT = null;

    protected $table = 'document_signatures';

    /** @var list<string> */
    protected $fillable = ['branch_id'];

    protected static function booted(): void
    {
        self::updating(function (): void {
            throw new LogicException('Bukti tanda tangan tidak dapat diubah.');
        });
        self::deleting(function (): void {
            throw new LogicException('Bukti tanda tangan tidak dapat dihapus.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['signed_at' => 'datetime', 'pdf_encrypted' => 'boolean'];
    }
}

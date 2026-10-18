<?php

declare(strict_types=1);

namespace Src\Shared\Application\Master;

/**
 * Master data yang boleh diisi lewat impor Excel (MST-09). `importKey` adalah kolom pengenal alami (mis. kode tindakan,
 * nama obat): baris yang pengenalnya sudah ada di klinik dilewati, tidak ditimpa dan tidak digandakan.
 */
interface ImportableMaster extends MasterEntity
{
    public function importKey(): string;
}

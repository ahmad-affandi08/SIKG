<?php

declare(strict_types=1);

namespace Src\Shared\Application\Master;

/**
 * Penanda impor yang pengenal alaminya boleh berulang (mis. beberapa batch untuk satu obat) dan yang efeknya menambah, bukan
 * membuat daftar master: baris tidak dilewati karena "sudah ada" dan tidak ditolak karena kembar di berkas. `importWarning` dipakai
 * untuk peringatan di pratinjau dan jendela konfirmasi.
 */
interface RepeatableImportKey
{
    public function importWarning(): string;

    /** Halaman tujuan setelah konfirmasi dan tautan kembali (entitas hanya-impor tidak punya halaman daftar master). */
    public function importHome(): string;
}

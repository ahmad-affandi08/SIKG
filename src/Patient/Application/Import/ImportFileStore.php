<?php

declare(strict_types=1);

namespace Src\Patient\Application\Import;

/**
 * Penyimpanan berkas impor di disk privat, dienkripsi di aplikasi bila diatur (berkas memuat data pasien).
 */
interface ImportFileStore
{
    public function put(string $contents): string;

    /**
     * Menyediakan salinan lokal sementara untuk dibaca, lalu menghapusnya.
     *
     * @template T
     *
     * @param  callable(string): T  $callback
     * @return T
     */
    public function withLocalCopy(string $path, callable $callback): mixed;

    public function delete(string $path): void;
}

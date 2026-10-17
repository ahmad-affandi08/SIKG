<?php

declare(strict_types=1);

namespace Src\Shared\Application;

/**
 * Pembuat PDF cetak (dokumen klinis, invoice, resep, laporan, odontogram). Satu tempat untuk ukuran kertas, metadata berkas,
 * dan penomoran halaman "Halaman X dari Y" agar semua cetakan seragam (ADR-0008).
 */
interface PdfRenderer
{
    public function render(PdfRequest $request): string;
}

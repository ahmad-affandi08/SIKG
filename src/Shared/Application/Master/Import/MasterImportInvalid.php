<?php

declare(strict_types=1);

namespace Src\Shared\Application\Master\Import;

use RuntimeException;

/** Berkas impor tidak dapat dipakai (tidak terbaca, kolom wajib tidak ada, kosong, atau terlalu besar). Pesan aman ditampilkan. */
final class MasterImportInvalid extends RuntimeException {}

<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Application\Install;

use RuntimeException;

final class AlreadyInstalled extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Sudah ada klinik di basis data ini. Penyiapan awal hanya dijalankan sekali.');
    }
}

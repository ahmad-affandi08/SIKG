<?php

declare(strict_types=1);

namespace Src\Integration\Infrastructure;

/** Menyelesaikan nama server menjadi alamat IP (dapat diganti pada tes agar tidak bergantung pada jaringan). */
interface HostResolver
{
    /** @return list<string> */
    public function resolve(string $host): array;
}

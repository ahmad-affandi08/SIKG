<?php

declare(strict_types=1);

namespace Src\Integration\Infrastructure;

final class SystemHostResolver implements HostResolver
{
    public function resolve(string $host): array
    {
        $ips = gethostbynamel($host) ?: [];
        foreach (dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return $ips;
    }
}

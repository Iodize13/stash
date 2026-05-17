<?php

namespace App\Services\Fetching;

interface HostResolver
{
    /**
     * @return list<string> Every IPv4 and IPv6 address the host resolves to.
     */
    public function resolve(string $host): array;
}

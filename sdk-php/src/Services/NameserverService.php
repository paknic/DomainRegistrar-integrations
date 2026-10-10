<?php

declare(strict_types=1);

namespace DomainRegistrar\SDK\Services;

use DomainRegistrar\SDK\Client;

class NameserverService
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function getNameservers(string $domain): array
    {
        return $this->client->request('GET', 'api/v1/nameservers/' . rawurlencode($domain));
    }

    public function saveNameservers(string $domain, array $nameservers): array
    {
        return $this->client->request('PUT', 'api/v1/nameservers/' . rawurlencode($domain), [
            'nameservers' => $nameservers
        ]);
    }

    public function getChildHosts(string $domain): array
    {
        return $this->client->request('GET', 'api/v1/nameservers/' . rawurlencode($domain) . '/hosts');
    }

    public function createChildHost(string $domain, string $host, array $ipv4 = [], array $ipv6 = []): array
    {
        return $this->client->request('POST', 'api/v1/nameservers/' . rawurlencode($domain) . '/hosts', [
            'host' => $host,
            'ipv4_addresses' => $ipv4,
            'ipv6_addresses' => $ipv6
        ]);
    }

    public function deleteChildHost(string $domain, string $host): array
    {
        return $this->client->request('DELETE', 'api/v1/nameservers/' . rawurlencode($domain) . '/hosts/' . rawurlencode($host));
    }
}

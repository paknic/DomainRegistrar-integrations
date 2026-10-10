<?php

declare(strict_types=1);

namespace DomainRegistrar\SDK\Services;

use DomainRegistrar\SDK\Client;

class DnsService
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function getZoneRecords(string $domain): array
    {
        return $this->client->request('GET', 'api/v1/dns/' . rawurlencode($domain));
    }

    public function saveZoneRecords(string $domain, array $records): array
    {
        return $this->client->request('POST', 'api/v1/dns/' . rawurlencode($domain), [
            'records' => $records
        ]);
    }

    public function getDnsSecRecords(string $domain): array
    {
        return $this->client->request('GET', 'api/v1/dns/' . rawurlencode($domain) . '/dnssec');
    }

    public function addDnsSecRecord(string $domain, int $keyTag, int $algorithm, int $digestType, string $digest): array
    {
        return $this->client->request('POST', 'api/v1/dns/' . rawurlencode($domain) . '/dnssec', [
            'key_tag' => $keyTag,
            'algorithm' => $algorithm,
            'digest_type' => $digestType,
            'digest' => $digest
        ]);
    }

    public function deleteDnsSecRecord(string $domain, int $keyTag): array
    {
        return $this->client->request('DELETE', 'api/v1/dns/' . rawurlencode($domain) . '/dnssec/' . $keyTag);
    }
}

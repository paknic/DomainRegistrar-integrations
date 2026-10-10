<?php

declare(strict_types=1);

namespace DomainRegistrar\SDK\Services;

use DomainRegistrar\SDK\Client;

class ContactService
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function getContacts(string $domain): array
    {
        return $this->client->request('GET', 'api/v1/contacts/' . rawurlencode($domain));
    }

    public function updateContacts(
        string $domain,
        array $registrantContact,
        ?array $adminContact = null,
        ?array $techContact = null,
        ?array $billingContact = null,
        bool $optOut60DayLock = true
    ): array {
        return $this->client->request('PUT', 'api/v1/contacts/' . rawurlencode($domain), [
            'registrant' => $registrantContact,
            'admin' => $adminContact,
            'tech' => $techContact,
            'billing' => $billingContact,
            'opt_out_60_day_transfer_lock' => $optOut60DayLock
        ]);
    }
}

<?php

declare(strict_types=1);

namespace DomainRegistrar\SDK\Services;

use DomainRegistrar\SDK\Client;

/**
 * TLD information that is not price: which extensions are winding down.
 */
class TldService
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * TLDs that are not simply on sale: sales_paused, sunsetting, migrating or retired. Each entry has tld, status,
     * registration_open, renewals_open, and (for a sunset) sunset_announced_at, no_new_registrations_after,
     * no_renewals_after and retire_at as ISO 8601 UTC strings or null.
     *
     * Stop selling these; renew existing domains only while renewals_open is true.
     */
    public function lifecycle(): array
    {
        return $this->client->request('GET', 'api/v1/tlds/lifecycle');
    }
}

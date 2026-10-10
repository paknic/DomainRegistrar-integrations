<?php

declare(strict_types=1);

namespace DomainRegistrar\SDK\Services;

use DomainRegistrar\SDK\Client;

class DomainService
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function check(string $domain): array
    {
        return $this->client->request('GET', 'api/v1/domains/check', ['domain' => $domain]);
    }

    public function bulkCheck(array $domains): array
    {
        return $this->client->request('POST', 'api/v1/domains/bulk-check', ['domains' => $domains]);
    }

    public function suggest(string $keyword, int $limit = 10): array
    {
        return $this->client->request('GET', 'api/v1/domains/suggest', ['keyword' => $keyword, 'limit' => $limit]);
    }

    /**
     * Registers a domain. $privacyEnabled and $dnsManagementEnabled buy the WhoisPrivacy and DnsManagement
     * add-ons for the same term, charged at their add-on prices.
     */
    public function register(
        string $domain,
        int $years,
        array $nameservers,
        array $registrantContact,
        bool $privacyEnabled = false,
        bool $autoRenew = true,
        bool $dnsManagementEnabled = false,
        ?array $adminContact = null,
        ?array $techContact = null,
        ?array $billingContact = null
    ): array {
        $payload = [
            'domain' => $domain,
            'years' => $years,
            'nameservers' => $nameservers,
            'registrant_contact' => $registrantContact,
            'privacy_enabled' => $privacyEnabled,
            'dns_management_enabled' => $dnsManagementEnabled,
            'auto_renew' => $autoRenew
        ];
        if ($adminContact !== null) $payload['admin_contact'] = $adminContact;
        if ($techContact !== null) $payload['tech_contact'] = $techContact;
        if ($billingContact !== null) $payload['billing_contact'] = $billingContact;

        return $this->client->request('POST', 'api/v1/domains/register', $payload);
    }

    public function renew(string $domain, int $years = 1, ?string $currentExpirationDate = null): array
    {
        return $this->client->request('POST', 'api/v1/domains/renew', [
            'domain' => $domain,
            'years' => $years,
            'current_expiration_date' => $currentExpirationDate
        ]);
    }

    public function transfer(
        string $domain,
        string $authCode,
        ?array $registrantContact = null,
        bool $privacyEnabled = false,
        bool $autoRenew = true
    ): array {
        return $this->client->request('POST', 'api/v1/domains/transfer', [
            'domain' => $domain,
            'auth_code' => $authCode,
            'registrant_contact' => $registrantContact,
            'privacy_enabled' => $privacyEnabled,
            'auto_renew' => $autoRenew
        ]);
    }

    public function getTransferStatus(string $domain): array
    {
        return $this->client->request('GET', 'api/v1/domains/transfer/status', ['domain' => $domain]);
    }

    public function getDetails(string $domain): array
    {
        return $this->client->request('GET', 'api/v1/domains/' . rawurlencode($domain));
    }

    public function listPortfolio(?string $status = null, ?string $search = null, int $page = 1, int $pageSize = 25): array
    {
        $params = ['page' => $page, 'pageSize' => $pageSize];
        if ($status !== null) $params['status'] = $status;
        if ($search !== null) $params['search'] = $search;

        return $this->client->request('GET', 'api/v1/domains', $params);
    }

    public function setLock(string $domain, bool $isLocked): array
    {
        return $this->client->request('PUT', 'api/v1/domains/' . rawurlencode($domain) . '/lock', ['is_transfer_locked' => $isLocked]);
    }

    /**
     * Whether a getDetails() result shows the customer transfer lock (clientTransferProhibited) set.
     */
    public static function isLocked(array $details): bool
    {
        return !empty($details['is_transfer_locked']);
    }

    public function setPrivacy(string $domain, bool $isPrivacyEnabled): array
    {
        return $this->client->request('PUT', 'api/v1/domains/' . rawurlencode($domain) . '/privacy', ['is_privacy_enabled' => $isPrivacyEnabled]);
    }

    public function setAutoRenew(string $domain, bool $autoRenew): array
    {
        return $this->client->request('PUT', 'api/v1/domains/' . rawurlencode($domain) . '/auto-renew', ['auto_renew' => $autoRenew]);
    }

    public function getAuthCode(string $domain): array
    {
        return $this->client->request('GET', 'api/v1/domains/' . rawurlencode($domain) . '/auth-code');
    }
}

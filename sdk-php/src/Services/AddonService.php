<?php

declare(strict_types=1);

namespace DomainRegistrar\SDK\Services;

use DomainRegistrar\SDK\Client;

/**
 * Per-domain paid add-on services. Privacy (PUT /domains/{d}/privacy) and DNS zone editing (POST /dns/{d})
 * are refused with ADDON_NOT_ACTIVE until the matching add-on is bought for the domain.
 */
class AddonService
{
    public const WHOIS_PRIVACY = 'WhoisPrivacy';
    public const DNS_MANAGEMENT = 'DnsManagement';

    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function listAddons(string $domain): array
    {
        return $this->client->request('GET', 'api/v1/domains/' . rawurlencode($domain) . '/addons');
    }

    /**
     * Buys (or extends) an add-on from the wallet. With $years null the API uses the rest of the domain's term.
     */
    public function purchase(string $domain, string $serviceCode, ?int $years = null): array
    {
        $payload = $years !== null ? ['years' => $years] : [];
        return $this->client->request('POST', 'api/v1/domains/' . rawurlencode($domain) . '/addons/' . rawurlencode($serviceCode), $payload);
    }

    public function setAutoRenew(string $domain, string $serviceCode, bool $autoRenew): array
    {
        return $this->client->request('PUT', 'api/v1/domains/' . rawurlencode($domain) . '/addons/' . rawurlencode($serviceCode) . '/auto-renew', [
            'auto_renew' => $autoRenew
        ]);
    }

    /**
     * Whether the add-on is active and not admin-disabled, from a listAddons() result.
     */
    public static function isActive(array $addons, string $serviceCode): bool
    {
        foreach ($addons as $addon) {
            if (($addon['service_code'] ?? null) === $serviceCode) {
                return !empty($addon['is_active']) && empty($addon['is_disabled_by_admin']);
            }
        }
        return false;
    }
}

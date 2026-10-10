<?php
/**
 * Paknic Domain Registrar Module for WHMCS
 *
 * Official Registrar Module for Paknic Automation Platform
 * Compatible with WHMCS 8.0 - 8.11+, PHP 7.4 through 8.3+
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// SDK bundled by Integrations/build.ps1, or installed with Composer.
if (file_exists(__DIR__ . '/lib/paknic-sdk/autoload.php')) {
    require_once __DIR__ . '/lib/paknic-sdk/autoload.php';
} elseif (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use DomainRegistrar\SDK\Client;
use DomainRegistrar\SDK\Configuration;
use DomainRegistrar\SDK\Exceptions\DomainRegistrarException;
use DomainRegistrar\SDK\Services\AddonService;
use DomainRegistrar\SDK\Services\DomainService;

/**
 * Returns configuration settings for WHMCS Admin -> Domain Registrars.
 */
function paknic_getConfigArray()
{
    return [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'Paknic Domain Registrar Automation',
        ],
        'ApiKey' => [
            'FriendlyName' => 'API Key (Key ID)',
            'Type' => 'text',
            'Size' => '40',
            'Description' => 'Your Paknic Reseller API Key ID. A <code>pk_test_...</code> key runs against the sandbox (nothing is registered or charged); a <code>pk_live_...</code> key is live.',
        ],
        'ApiSecret' => [
            'FriendlyName' => 'API Secret',
            'Type' => 'password',
            'Size' => '50',
            'Description' => 'The secret shown once when the key was created (<code>sk_test_...</code> or <code>sk_live_...</code>).',
        ],
        'ApiUrl' => [
            'FriendlyName' => 'API Base URL',
            'Type' => 'text',
            'Size' => '50',
            'Default' => 'https://api.paknic.com',
            'Description' => 'API host only, e.g. <code>https://api.paknic.com</code> (do not add /api/v1).',
        ],
    ];
}

/**
 * Helper to instantiate SDK Client.
 */
function paknic_getClient(array $params): Client
{
    $config = new Configuration(
        $params['ApiKey'] ?? '',
        $params['ApiSecret'] ?? '',
        !empty($params['ApiUrl']) ? $params['ApiUrl'] : Configuration::DEFAULT_BASE_URL
    );
    return new Client($config);
}

function paknic_domainName(array $params): string
{
    return $params['sld'] . '.' . $params['tld'];
}

/**
 * The phone number in the API's +CC.NNNN form. WHMCS supplies fullphonenumber in that form already when
 * phone number formatting is on.
 */
function paknic_formatPhone(array $params, string $prefix = ''): string
{
    $full = $params[$prefix . 'fullphonenumber'] ?? '';
    if (preg_match('/^\+\d{1,3}\.\d+$/', $full)) {
        return $full;
    }
    $cc = preg_replace('/[^0-9]/', '', (string)($params[$prefix . 'phonecc'] ?? ''));
    $number = preg_replace('/[^0-9]/', '', (string)($params[$prefix . 'phonenumber'] ?? ''));
    if ($cc !== '' && strpos($number, $cc) === 0 && strlen($number) > 10) {
        $number = substr($number, strlen($cc));
    }
    return '+' . ($cc !== '' ? $cc : '1') . '.' . $number;
}

/**
 * Buys an add-on for the domain unless it is already active. Returns an error string, or null on success.
 */
function paknic_ensureAddon(Client $client, string $domain, string $serviceCode): ?string
{
    try {
        $addons = $client->addons()->listAddons($domain);
        if (AddonService::isActive($addons, $serviceCode)) {
            return null;
        }
        $client->addons()->purchase($domain, $serviceCode);
        return null;
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

/**
 * Check Domain Availability.
 */
function paknic_CheckAvailability(array $params)
{
    $client = paknic_getClient($params);
    $searchTerm = !empty($params['isIdnDomain']) && !empty($params['punyCodeSearchTerm']) ? $params['punyCodeSearchTerm'] : $params['searchTerm'];
    $tlds = $params['tldsToInclude'];

    $results = new \WHMCS\Domains\DomainLookup\ResultsList();

    foreach ($tlds as $tld) {
        $searchResult = new \WHMCS\Domains\DomainLookup\SearchResult($searchTerm, $tld);
        try {
            $check = $client->domains()->check($searchTerm . $tld);
            $searchResult->setStatus(!empty($check['is_available'])
                ? \WHMCS\Domains\DomainLookup\SearchResult::STATUS_NOT_REGISTERED
                : \WHMCS\Domains\DomainLookup\SearchResult::STATUS_REGISTERED);
        } catch (DomainRegistrarException $e) {
            // One unsupported or failing TLD must not hide the results for the others.
            $searchResult->setStatus($e->getErrorCode() === 'TLD_NOT_SUPPORTED'
                ? \WHMCS\Domains\DomainLookup\SearchResult::STATUS_TLD_NOT_SUPPORTED
                : \WHMCS\Domains\DomainLookup\SearchResult::STATUS_UNKNOWN);
        }
        $results->append($searchResult);
    }

    return $results;
}

/**
 * Registrar TLD Sync (Utilities > Registrar TLD Sync): imports the prices this key is charged.
 */
function paknic_GetTldPricing(array $params)
{
    try {
        $pricing = paknic_getClient($params)->account()->getPricing();
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }

    $results = new \WHMCS\Results\ResultsList();
    foreach ($pricing as $tld) {
        $item = (new \WHMCS\Domain\TopLevel\ImportItem())
            ->setExtension($tld['tld'])
            ->setMinYears((int)($tld['min_years'] ?? 1))
            ->setMaxYears((int)($tld['max_years'] ?? 10))
            ->setRegisterPrice((float)$tld['register_price'])
            ->setRenewPrice((float)$tld['renew_price'])
            ->setTransferPrice((float)$tld['transfer_price'])
            ->setRedemptionFeeDays((int)($tld['redemption_period_days'] ?? 30))
            ->setRedemptionFeePrice((float)($tld['redemption_price'] ?? 0))
            ->setCurrency($tld['currency'] ?? 'USD')
            ->setEppRequired(!empty($tld['epp_code_required_for_transfer']));
        $results->append($item);
    }
    return $results;
}

/**
 * Register Domain.
 */
function paknic_RegisterDomain(array $params)
{
    $client = paknic_getClient($params);
    $domain = paknic_domainName($params);

    $nameservers = [];
    for ($i = 1; $i <= 5; $i++) {
        if (!empty($params['ns' . $i])) {
            $nameservers[] = $params['ns' . $i];
        }
    }

    $registrant = [
        'first_name' => $params['firstname'],
        'last_name' => $params['lastname'],
        'organization' => $params['companyname'] ?? '',
        'address1' => $params['address1'],
        'address2' => $params['address2'] ?? '',
        'city' => $params['city'],
        'state' => $params['state'],
        'postal_code' => $params['postcode'],
        'country_code' => $params['countrycode'] ?? $params['country'],
        'email' => $params['email'],
        'phone' => paknic_formatPhone($params),
    ];

    try {
        // ID protection and DNS management sold in WHMCS are bought as the matching paid add-ons.
        $client->domains()->register(
            $domain,
            (int)$params['regperiod'],
            $nameservers,
            $registrant,
            !empty($params['idprotection']),
            true,
            !empty($params['dnsmanagement'])
        );

        return ['success' => true];
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Renew Domain.
 */
function paknic_RenewDomain(array $params)
{
    $client = paknic_getClient($params);
    $domain = paknic_domainName($params);

    try {
        $client->domains()->renew($domain, (int)$params['regperiod']);
        return ['success' => true];
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Transfer Domain.
 */
function paknic_TransferDomain(array $params)
{
    $client = paknic_getClient($params);
    $domain = paknic_domainName($params);
    $authCode = $params['eppcode'] ?? $params['transfersecret'] ?? '';

    try {
        $client->domains()->transfer($domain, $authCode, null, !empty($params['idprotection']));
        return ['success' => true];
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Get Nameservers.
 */
function paknic_GetNameservers(array $params)
{
    $client = paknic_getClient($params);

    try {
        $data = $client->nameservers()->getNameservers(paknic_domainName($params));
        $ns = $data['nameservers'] ?? [];
        $values = [];
        for ($i = 0; $i < 5; $i++) {
            $values['ns' . ($i + 1)] = $ns[$i] ?? '';
        }
        return $values;
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Save Nameservers.
 */
function paknic_SaveNameservers(array $params)
{
    $client = paknic_getClient($params);

    $nameservers = [];
    for ($i = 1; $i <= 5; $i++) {
        if (!empty($params['ns' . $i])) {
            $nameservers[] = $params['ns' . $i];
        }
    }

    try {
        $client->nameservers()->saveNameservers(paknic_domainName($params), $nameservers);
        return ['success' => true];
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Get Registrar Lock.
 */
function paknic_GetRegistrarLock(array $params)
{
    $client = paknic_getClient($params);

    try {
        $info = $client->domains()->getDetails(paknic_domainName($params));
        return DomainService::isLocked($info) ? 'locked' : 'unlocked';
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Save Registrar Lock.
 */
function paknic_SaveRegistrarLock(array $params)
{
    $client = paknic_getClient($params);
    $lockStatus = $params['lockenabled'] === 'locked';

    try {
        $client->domains()->setLock(paknic_domainName($params), $lockStatus);
        return ['success' => true];
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Get EPP Auth-Code.
 */
function paknic_GetEPPCode(array $params)
{
    $client = paknic_getClient($params);

    try {
        $res = $client->domains()->getAuthCode(paknic_domainName($params));
        return ['eppcode' => $res['auth_code'] ?? ''];
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Toggle ID Protection. Turning it on buys the WhoisPrivacy add-on if the domain doesn't have it; turning it
 * off also stops the add-on renewing, since the customer is no longer paying for it in WHMCS.
 */
function paknic_IDProtectToggle(array $params)
{
    $client = paknic_getClient($params);
    $domain = paknic_domainName($params);
    $enable = (bool)$params['protectenable'];

    try {
        if ($enable) {
            $error = paknic_ensureAddon($client, $domain, AddonService::WHOIS_PRIVACY);
            if ($error !== null) {
                return ['error' => $error];
            }
        }

        $client->domains()->setPrivacy($domain, $enable);

        if (!$enable) {
            try {
                $client->addons()->setAutoRenew($domain, AddonService::WHOIS_PRIVACY, false);
            } catch (Exception $ignored) {
                // Privacy is already off; the add-on simply keeps its current renewal setting.
            }
        }
        return ['success' => true];
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * API contact → WHMCS contact fields.
 */
function paknic_toWhmcsContact(?array $c): array
{
    $c = $c ?? [];
    return [
        'First Name' => $c['first_name'] ?? '',
        'Last Name' => $c['last_name'] ?? '',
        'Company Name' => $c['organization'] ?? '',
        'Address 1' => $c['address1'] ?? '',
        'Address 2' => $c['address2'] ?? '',
        'City' => $c['city'] ?? '',
        'State' => $c['state'] ?? '',
        'Postcode' => $c['postal_code'] ?? '',
        'Country' => $c['country_code'] ?? '',
        'Email Address' => $c['email'] ?? '',
        'Phone Number' => $c['phone'] ?? '',
    ];
}

/**
 * WHMCS contact fields → API contact, or null when the section was not submitted.
 */
function paknic_fromWhmcsContact(?array $c): ?array
{
    if (empty($c) || trim(($c['First Name'] ?? '') . ($c['Email Address'] ?? '')) === '') {
        return null;
    }
    return [
        'first_name' => $c['First Name'] ?? '',
        'last_name' => $c['Last Name'] ?? '',
        'organization' => $c['Company Name'] ?? '',
        'address1' => $c['Address 1'] ?? '',
        'address2' => $c['Address 2'] ?? '',
        'city' => $c['City'] ?? '',
        'state' => $c['State'] ?? '',
        'postal_code' => $c['Postcode'] ?? '',
        'country_code' => $c['Country'] ?? '',
        'email' => $c['Email Address'] ?? '',
        'phone' => $c['Phone Number'] ?? '',
    ];
}

/**
 * Get Contact Details.
 */
function paknic_GetContactDetails(array $params)
{
    $client = paknic_getClient($params);

    try {
        // GET /contacts/{domain} returns registrant/admin/tech/billing at the top of data.
        $data = $client->contacts()->getContacts(paknic_domainName($params));

        return [
            'Registrant' => paknic_toWhmcsContact($data['registrant'] ?? null),
            'Admin' => paknic_toWhmcsContact($data['admin'] ?? $data['registrant'] ?? null),
            'Technical' => paknic_toWhmcsContact($data['tech'] ?? $data['registrant'] ?? null),
            'Billing' => paknic_toWhmcsContact($data['billing'] ?? $data['registrant'] ?? null),
        ];
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Save Contact Details.
 */
function paknic_SaveContactDetails(array $params)
{
    $client = paknic_getClient($params);
    $details = $params['contactdetails'] ?? [];

    $registrant = paknic_fromWhmcsContact($details['Registrant'] ?? null);
    if ($registrant === null) {
        return ['error' => 'Registrant contact details are required.'];
    }

    try {
        $client->contacts()->updateContacts(
            paknic_domainName($params),
            $registrant,
            paknic_fromWhmcsContact($details['Admin'] ?? null),
            paknic_fromWhmcsContact($details['Technical'] ?? null),
            paknic_fromWhmcsContact($details['Billing'] ?? null)
        );
        return ['success' => true];
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Get DNS Zone Records.
 */
function paknic_GetDNS(array $params)
{
    $client = paknic_getClient($params);

    try {
        $res = $client->dns()->getZoneRecords(paknic_domainName($params));
        $records = [];
        foreach ($res['records'] ?? [] as $r) {
            $records[] = [
                'hostname' => $r['name'],
                'type' => $r['type'],
                'address' => $r['content'],
                'priority' => $r['priority'] ?? '',
            ];
        }
        return $records;
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Save DNS Zone Records. Saving replaces the zone, so record types the WHMCS editor cannot show (SRV, CAA, NS...)
 * are carried over from the current zone rather than deleted.
 */
function paknic_SaveDNS(array $params)
{
    $client = paknic_getClient($params);
    $domain = paknic_domainName($params);
    $whmcsTypes = ['A', 'AAAA', 'CNAME', 'MX', 'MXE', 'TXT', 'URL', 'FRAME'];

    $records = [];
    foreach ($params['dnsrecords'] ?? [] as $r) {
        if (!empty($r['address'])) {
            $records[] = [
                'name' => ($r['hostname'] ?? '') !== '' ? $r['hostname'] : '@',
                'type' => $r['type'] ?? 'A',
                'content' => $r['address'],
                'priority' => !empty($r['priority']) ? (int)$r['priority'] : null,
                'ttl' => 3600,
            ];
        }
    }

    try {
        $current = $client->dns()->getZoneRecords($domain);
        foreach ($current['records'] ?? [] as $r) {
            if (!in_array(strtoupper($r['type'] ?? ''), $whmcsTypes, true)) {
                unset($r['id']);
                $records[] = $r;
            }
        }
    } catch (Exception $ignored) {
        // No zone yet: nothing to carry over.
    }

    try {
        $client->dns()->saveZoneRecords($domain, $records);
        return ['success' => true];
    } catch (DomainRegistrarException $e) {
        // DNS editing needs the DnsManagement add-on. Buy it when the customer has DNS Management in WHMCS.
        if ($e->getErrorCode() === 'ADDON_NOT_ACTIVE' && !empty($params['dnsmanagement'])) {
            $error = paknic_ensureAddon($client, $domain, AddonService::DNS_MANAGEMENT);
            if ($error !== null) {
                return ['error' => $error];
            }
            try {
                $client->dns()->saveZoneRecords($domain, $records);
                return ['success' => true];
            } catch (Exception $retry) {
                return ['error' => $retry->getMessage()];
            }
        }
        return ['error' => $e->getMessage()];
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Register Child Nameserver (Glue Record).
 */
function paknic_RegisterNameserver(array $params)
{
    $client = paknic_getClient($params);
    $ip = $params['ipaddress'];
    $isV6 = strpos($ip, ':') !== false;

    try {
        $client->nameservers()->createChildHost(paknic_domainName($params), $params['nameserver'], $isV6 ? [] : [$ip], $isV6 ? [$ip] : []);
        return ['success' => true];
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Delete Child Nameserver.
 */
function paknic_DeleteNameserver(array $params)
{
    $client = paknic_getClient($params);

    try {
        $client->nameservers()->deleteChildHost(paknic_domainName($params), $params['nameserver']);
        return ['success' => true];
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Domain Sync Cron Handler.
 */
function paknic_Sync(array $params)
{
    $client = paknic_getClient($params);

    try {
        $details = $client->domains()->getDetails(paknic_domainName($params));
        $status = $details['status'] ?? '';
        $result = [
            // Court-order and UDRP locks keep the domain registered; it just cannot be changed.
            'active' => in_array($status, ['Active', 'RegistrarLock', 'CourtOrderLock', 'UdrpDispute'], true),
            'expired' => in_array($status, ['Expired', 'Redemption'], true),
            'cancelled' => in_array($status, ['Cancelled', 'Inactive'], true),
        ];
        if (!empty($details['expiration_date_utc'])) {
            $result['expirydate'] = substr($details['expiration_date_utc'], 0, 10);
        }
        return $result;
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Transfer Sync Cron Handler.
 */
function paknic_TransferSync(array $params)
{
    $client = paknic_getClient($params);
    $domain = paknic_domainName($params);

    try {
        $res = $client->domains()->getTransferStatus($domain);
        $status = strtolower($res['status'] ?? '');

        if ($status === 'completed') {
            $details = $client->domains()->getDetails($domain);
            return ['completed' => true, 'expirydate' => substr($details['expiration_date_utc'], 0, 10)];
        }
        if (in_array($status, ['rejected', 'cancelled', 'expired'], true)) {
            $reason = $res['reason'] ?? ($status === 'expired'
                ? 'The transfer was not approved in time.'
                : 'Transfer ' . $status . '.');
            return ['failed' => true, 'reason' => $reason];
        }

        return []; // Still pending (PendingFoa, FoaConfirmed, SubmittedToRegistry)
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

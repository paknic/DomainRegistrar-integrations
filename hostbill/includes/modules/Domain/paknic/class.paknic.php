<?php
/**
 * Paknic Registrar Module for HostBill
 *
 * HostBill calls these methods without arguments: the domain is in $this->options (sld, tld, ns1..ns5, and the
 * form fields of the action), the term in $this->period, stored values in $this->details, and the order's
 * contacts in $this->domain_contacts. Results go back through addInfo()/addError() and the return value.
 */

require_once __DIR__ . '/lib/paknic-sdk/autoload.php';

use DomainRegistrar\SDK\Client;
use DomainRegistrar\SDK\Configuration;
use DomainRegistrar\SDK\Exceptions\DomainRegistrarException;
use DomainRegistrar\SDK\Services\AddonService;
use DomainRegistrar\SDK\Services\DomainService;

class paknic extends DomainModule
{
    protected $description = 'Paknic domain registration, transfer, renewal, contacts, nameservers, lock, ID protection and DNS';
    protected $modname = 'Paknic';
    protected $version = '2.0.0';

    protected $configuration = [
        'api_key' => [
            'value' => '',
            'type' => 'input',
            'default' => false,
        ],
        'api_secret' => [
            'value' => '',
            'type' => 'password',
            'default' => false,
        ],
        'api_url' => [
            'value' => 'https://api.paknic.com',
            'type' => 'input',
            'default' => 'https://api.paknic.com',
        ],
    ];

    protected $lang = [
        'english' => [
            'api_key' => 'API Key ID (pk_live_... or pk_test_... for the sandbox)',
            'api_secret' => 'API Secret',
            'api_url' => 'API Base URL (host only, e.g. https://api.paknic.com)',
        ],
    ];

    protected $commands = [
        'Register', 'Transfer', 'Renew',
        'getNameServers', 'updateNameServers',
        'getContactInfo', 'updateContactInfo',
        'getEppCode',
        'registerNameServer', 'modifyNameServer', 'deleteNameServer',
        'getRegistrarLock', 'updateRegistrarLock',
        'updateIDProtection',
        'getDNSmanagement', 'updateDNSManagement',
    ];

    private function client(): Client
    {
        return new Client(new Configuration(
            (string)$this->configuration['api_key']['value'],
            (string)$this->configuration['api_secret']['value'],
            (string)($this->configuration['api_url']['value'] ?: Configuration::DEFAULT_BASE_URL)
        ));
    }

    private function domainName(): string
    {
        return $this->options['sld'] . '.' . ltrim($this->options['tld'], '.');
    }

    private function nameservers(): array
    {
        $list = [];
        for ($i = 1; $i <= 5; $i++) {
            $ns = !empty($this->options['ns' . $i]) ? $this->options['ns' . $i] : ($this->details['ns' . $i] ?? '');
            if ($ns !== '') {
                $list[] = $ns;
            }
        }
        return $list;
    }

    private static function phone(?string $number): string
    {
        $number = trim((string)$number);
        if (preg_match('/^\+\d{1,3}\.\d+$/', $number)) {
            return $number;
        }
        $digits = preg_replace('/[^0-9]/', '', $number);
        return '+' . $digits;
    }

    /** HostBill contact (firstname, companyname, postcode, phonenumber...) → API contact. */
    private static function toApiContact(array $c): array
    {
        return [
            'first_name' => $c['firstname'] ?? '',
            'last_name' => $c['lastname'] ?? '',
            'organization' => $c['companyname'] ?? '',
            'address1' => $c['address1'] ?? '',
            'address2' => $c['address2'] ?? '',
            'city' => $c['city'] ?? '',
            'state' => $c['state'] ?? '',
            'postal_code' => $c['postcode'] ?? '',
            'country_code' => strtoupper($c['country'] ?? ''),
            'email' => $c['email'] ?? '',
            'phone' => self::phone($c['phonenumber'] ?? ''),
        ];
    }

    /** API contact → HostBill contact. */
    private static function toHostBillContact(?array $c): array
    {
        $c = $c ?? [];
        return [
            'firstname' => $c['first_name'] ?? '',
            'lastname' => $c['last_name'] ?? '',
            'companyname' => $c['organization'] ?? '',
            'address1' => $c['address1'] ?? '',
            'address2' => $c['address2'] ?? '',
            'city' => $c['city'] ?? '',
            'state' => $c['state'] ?? '',
            'postcode' => $c['postal_code'] ?? '',
            'country' => $c['country_code'] ?? '',
            'email' => $c['email'] ?? '',
            'phonenumber' => $c['phone'] ?? '',
        ];
    }

    private function fail(Exception $e): bool
    {
        $this->addError($e->getMessage());
        return false;
    }

    /** Buys the add-on unless the domain already has it. */
    private function ensureAddon(Client $client, string $domain, string $code): void
    {
        if (!AddonService::isActive($client->addons()->listAddons($domain), $code)) {
            $client->addons()->purchase($domain, $code);
        }
    }

    public function testConnection()
    {
        try {
            $this->client()->account()->getBalance();
            return true;
        } catch (Exception $e) {
            return $this->fail($e);
        }
    }

    public function Register()
    {
        $contacts = $this->domain_contacts ?? [];
        $registrant = $contacts['registrant'] ?? [];
        if (empty($registrant)) {
            $this->addError('The order has no registrant contact.');
            return false;
        }

        try {
            $result = $this->client()->domains()->register(
                $this->domainName(),
                max(1, (int)$this->period),
                $this->nameservers(),
                self::toApiContact($registrant),
                !empty($this->options['idprotection']),
                true,
                !empty($this->options['dnsmanagement']),
                !empty($contacts['admin']) ? self::toApiContact($contacts['admin']) : null,
                !empty($contacts['tech']) ? self::toApiContact($contacts['tech']) : null,
                !empty($contacts['billing']) ? self::toApiContact($contacts['billing']) : null
            );
            $this->addInfo(($result['status'] ?? '') === 'Pending'
                ? 'Registration submitted; the registry has not confirmed it yet. Domain sync will update it.'
                : 'Domain has been registered');
            return true;
        } catch (Exception $e) {
            return $this->fail($e);
        }
    }

    public function Transfer()
    {
        $authCode = $this->details['epp_code'] ?? ($this->options['epp_code'] ?? '');
        if ($authCode === '') {
            $this->addError('An EPP/auth code is required to transfer the domain.');
            return false;
        }
        $registrant = $this->domain_contacts['registrant'] ?? [];

        try {
            $this->client()->domains()->transfer(
                $this->domainName(),
                $authCode,
                !empty($registrant) ? self::toApiContact($registrant) : null,
                !empty($this->options['idprotection'])
            );
            $this->addInfo('Transfer has been requested');
            return true;
        } catch (Exception $e) {
            return $this->fail($e);
        }
    }

    public function Renew()
    {
        try {
            $result = $this->client()->domains()->renew($this->domainName(), max(1, (int)$this->period));
            $this->addInfo('Domain has been renewed' . (!empty($result['new_expiration_date_utc'])
                ? ' until ' . substr($result['new_expiration_date_utc'], 0, 10) : ''));
            return true;
        } catch (Exception $e) {
            return $this->fail($e);
        }
    }

    public function getNameServers()
    {
        try {
            $ns = $this->client()->nameservers()->getNameservers($this->domainName())['nameservers'] ?? [];
            $result = [];
            for ($i = 1; $i <= 5; $i++) {
                $result['ns' . $i] = $ns[$i - 1] ?? '';
                $this->details['ns' . $i] = $result['ns' . $i];
            }
            return $result;
        } catch (Exception $e) {
            return $this->fail($e);
        }
    }

    public function updateNameServers()
    {
        try {
            $this->client()->nameservers()->saveNameservers($this->domainName(), $this->nameservers());
            $this->addInfo('Nameservers have been updated at the registrar');
            return true;
        } catch (Exception $e) {
            return $this->fail($e);
        }
    }

    public function getContactInfo()
    {
        try {
            $data = $this->client()->contacts()->getContacts($this->domainName());
            return [
                'registrant' => self::toHostBillContact($data['registrant'] ?? null),
                'admin' => self::toHostBillContact($data['admin'] ?? $data['registrant'] ?? null),
                'tech' => self::toHostBillContact($data['tech'] ?? $data['registrant'] ?? null),
                'billing' => self::toHostBillContact($data['billing'] ?? $data['registrant'] ?? null),
            ];
        } catch (Exception $e) {
            return $this->fail($e);
        }
    }

    public function updateContactInfo()
    {
        // Either per-role arrays (registrant/admin/tech/billing) or a single flat registrant form.
        $roles = [];
        foreach (['registrant', 'admin', 'tech', 'billing'] as $role) {
            if (!empty($this->options[$role]) && is_array($this->options[$role])) {
                $roles[$role] = self::toApiContact($this->options[$role]);
            }
        }
        if (empty($roles['registrant']) && !empty($this->options['firstname'])) {
            $roles['registrant'] = self::toApiContact($this->options);
        }
        if (empty($roles['registrant'])) {
            $this->addError('Registrant contact details are required.');
            return false;
        }

        try {
            $this->client()->contacts()->updateContacts(
                $this->domainName(),
                $roles['registrant'],
                $roles['admin'] ?? null,
                $roles['tech'] ?? null,
                $roles['billing'] ?? null
            );
            $this->addInfo('Contact details have been updated');
            return true;
        } catch (Exception $e) {
            return $this->fail($e);
        }
    }

    public function getEppCode()
    {
        try {
            $code = $this->client()->domains()->getAuthCode($this->domainName())['auth_code'] ?? '';
            $this->details['epp_code'] = $code;
            $this->addInfo('EPP Code: ' . $code);
            return $code;
        } catch (Exception $e) {
            return $this->fail($e);
        }
    }

    private function childHost(): string
    {
        $host = trim((string)($this->options['NameServer'] ?? ''));
        $suffix = '.' . $this->domainName();
        return substr($host, -strlen($suffix)) === $suffix ? $host : $host . $suffix;
    }

    private static function splitIps(string ...$ips): array
    {
        $v4 = $v6 = [];
        foreach ($ips as $ip) {
            $ip = trim($ip);
            if ($ip === '') continue;
            if (strpos($ip, ':') !== false) $v6[] = $ip; else $v4[] = $ip;
        }
        return [$v4, $v6];
    }

    public function registerNameServer()
    {
        [$v4, $v6] = self::splitIps((string)($this->options['NameServerIP'] ?? ''));
        try {
            $this->client()->nameservers()->createChildHost($this->domainName(), $this->childHost(), $v4, $v6);
            $this->addInfo('Child nameserver has been registered');
            return true;
        } catch (Exception $e) {
            return $this->fail($e);
        }
    }

    /** The API has no host update: the host is re-created with the new address. */
    public function modifyNameServer()
    {
        $client = $this->client();
        $host = $this->childHost();
        [$v4, $v6] = self::splitIps((string)($this->options['NameServerNewIP'] ?? ''));
        try {
            $client->nameservers()->deleteChildHost($this->domainName(), $host);
            $client->nameservers()->createChildHost($this->domainName(), $host, $v4, $v6);
            $this->addInfo('Child nameserver has been updated');
            return true;
        } catch (Exception $e) {
            return $this->fail($e);
        }
    }

    public function deleteNameServer()
    {
        try {
            $this->client()->nameservers()->deleteChildHost($this->domainName(), $this->childHost());
            $this->addInfo('Child nameserver has been deleted');
            return true;
        } catch (Exception $e) {
            return $this->fail($e);
        }
    }

    public function getRegistrarLock()
    {
        try {
            return DomainService::isLocked($this->client()->domains()->getDetails($this->domainName()));
        } catch (Exception $e) {
            return $this->fail($e);
        }
    }

    public function updateRegistrarLock()
    {
        // HostBill's domain record calls the lock "reglock".
        $lock = !empty($this->options['reglock']) || !empty($this->options['registrarLock']);
        try {
            $this->client()->domains()->setLock($this->domainName(), $lock);
            $this->addInfo('Registrar lock has been ' . ($lock ? 'enabled' : 'disabled'));
            return true;
        } catch (Exception $e) {
            return $this->fail($e);
        }
    }

    /** Turning ID protection on buys the WhoisPrivacy add-on (paid) if the domain doesn't have it. */
    public function updateIDProtection()
    {
        $enable = !empty($this->options['idprotection']);
        $client = $this->client();
        $domain = $this->domainName();
        try {
            if ($enable) {
                $this->ensureAddon($client, $domain, AddonService::WHOIS_PRIVACY);
            }
            $client->domains()->setPrivacy($domain, $enable);
            if (!$enable) {
                try {
                    $client->addons()->setAutoRenew($domain, AddonService::WHOIS_PRIVACY, false);
                } catch (Exception $ignored) {
                }
            }
            $this->addInfo('ID protection has been ' . ($enable ? 'enabled' : 'disabled'));
            return true;
        } catch (Exception $e) {
            return $this->fail($e);
        }
    }

    /** @return array list of ['name','type','content','priority','ttl'] records */
    public function getDNSmanagement()
    {
        try {
            $records = $this->client()->dns()->getZoneRecords($this->domainName())['records'] ?? [];
            return array_map(static function ($r) {
                return [
                    'name' => $r['name'] ?? '@',
                    'type' => $r['type'] ?? 'A',
                    'content' => $r['content'] ?? '',
                    'priority' => $r['priority'] ?? '',
                    'ttl' => $r['ttl'] ?? 3600,
                ];
            }, $records);
        } catch (Exception $e) {
            return $this->fail($e);
        }
    }

    /**
     * Replaces the zone with $this->options['records']. Needs the DnsManagement add-on, which is bought (paid)
     * on first use when the domain has DNS management in HostBill.
     */
    public function updateDNSManagement()
    {
        $records = [];
        foreach ((array)($this->options['records'] ?? []) as $r) {
            if (empty($r['content'])) continue;
            $records[] = [
                'name' => ($r['name'] ?? '') !== '' ? $r['name'] : '@',
                'type' => strtoupper($r['type'] ?? 'A'),
                'content' => $r['content'],
                'priority' => isset($r['priority']) && $r['priority'] !== '' ? (int)$r['priority'] : null,
                'ttl' => (int)($r['ttl'] ?? 3600),
            ];
        }

        $client = $this->client();
        $domain = $this->domainName();
        try {
            try {
                $client->dns()->saveZoneRecords($domain, $records);
            } catch (DomainRegistrarException $e) {
                if ($e->getErrorCode() !== 'ADDON_NOT_ACTIVE' || empty($this->options['dnsmanagement'])) {
                    throw $e;
                }
                $this->ensureAddon($client, $domain, AddonService::DNS_MANAGEMENT);
                $client->dns()->saveZoneRecords($domain, $records);
            }
            $this->addInfo('DNS records have been updated');
            return true;
        } catch (Exception $e) {
            return $this->fail($e);
        }
    }

    /**
     * Domain sync: returns the registry state for HostBill to store.
     */
    public function synchInfo()
    {
        try {
            $details = $this->client()->domains()->getDetails($this->domainName());
        } catch (Exception $e) {
            return $this->fail($e);
        }

        $status = $details['status'] ?? '';
        $map = [
            'Active' => 'Active', 'RegistrarLock' => 'Active', 'CourtOrderLock' => 'Active', 'UdrpDispute' => 'Active',
            'Expired' => 'Expired', 'Redemption' => 'Expired',
            'Processing' => 'Pending Registration', 'PendingTransfer' => 'Pending Transfer',
            'Cancelled' => 'Cancelled', 'Inactive' => 'Cancelled',
        ];
        $result = [
            'expires' => substr((string)($details['expiration_date_utc'] ?? ''), 0, 10),
            'status' => $map[$status] ?? 'Active',
            // Keys follow HostBill's domain record (Admin API getDomainDetails).
            'nameservers' => $details['nameservers'] ?? [],
            'ns' => $details['nameservers'] ?? [],
            'reglock' => DomainService::isLocked($details),
            'idprotection' => !empty($details['is_privacy_enabled']),
            'autorenew' => !empty($details['auto_renew']),
        ];
        $this->addInfo('Domain synchronised: ' . $result['status'] . ', expires ' . $result['expires']);
        return $result;
    }
}

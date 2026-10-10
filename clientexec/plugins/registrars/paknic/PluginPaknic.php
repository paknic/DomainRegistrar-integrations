<?php
/**
 * Paknic Registrar Plugin for Clientexec
 *
 * Official Registrar Plugin for Paknic Automation Platform
 * Compatible with Clientexec 6.x / 7.x
 */

require_once __DIR__ . '/lib/paknic-sdk/autoload.php';

use DomainRegistrar\SDK\Client;
use DomainRegistrar\SDK\Configuration;

if (file_exists(dirname(__FILE__) . '/../../models/registrars/RegistrarPlugin.php')) {
    require_once dirname(__FILE__) . '/../../models/registrars/RegistrarPlugin.php';
}

class PluginPaknic extends RegistrarPlugin
{
    // Only what this plugin implements (preview): check, register, renew, EPP code.
    public $features = [
        'nameSuggest' => false,
        'hasDNS' => false,
        'hasPrivacy' => false,
        'hasContactChange' => false,
        'hasEppCode' => true,
        'hasLock' => false,
        'hasChildNameServers' => false,
    ];

    public function getVariables()
    {
        return [
            'Plugin Name' => [
                'type' => 'hidden',
                'description' => 'Paknic Registrar Automation',
                'value' => 'Paknic',
            ],
            'API Key' => [
                'type' => 'text',
                'description' => 'Enter your Paknic Reseller API Key ID',
                'value' => '',
            ],
            'API Secret' => [
                'type' => 'password',
                'description' => 'Enter your Paknic Reseller API Secret Key',
                'value' => '',
            ],
            'API URL' => [
                'type' => 'text',
                'description' => 'API host only, e.g. https://api.paknic.com. A pk_test_ key uses the sandbox.',
                'value' => 'https://api.paknic.com',
            ],
        ];
    }

    private function getSdkClient(): Client
    {
        $config = new Configuration(
            $this->settings->get('plugin_paknic_API Key'),
            $this->settings->get('plugin_paknic_API Secret'),
            $this->settings->get('plugin_paknic_API URL') ?: Configuration::DEFAULT_BASE_URL
        );
        return new Client($config);
    }

    public function checkDomain($params)
    {
        $client = $this->getSdkClient();
        $domain = $params['sld'] . '.' . $params['tld'];

        try {
            $res = $client->domains()->check($domain);
            return !empty($res['is_available']) ? 0 : 1; // 0 = available in CE
        } catch (Exception $e) {
            return 1;
        }
    }

    public function registerDomain($params)
    {
        $client = $this->getSdkClient();
        $domain = $params['sld'] . '.' . $params['tld'];

        $nameservers = [];
        if (!empty($params['NS1'])) $nameservers[] = $params['NS1'];
        if (!empty($params['NS2'])) $nameservers[] = $params['NS2'];

        $registrant = [
            'first_name' => $params['RegistrantFirstName'] ?? '',
            'last_name' => $params['RegistrantLastName'] ?? '',
            'organization' => $params['RegistrantOrganizationName'] ?? '',
            'address1' => $params['RegistrantAddress1'] ?? '',
            'city' => $params['RegistrantCity'] ?? '',
            'state' => $params['RegistrantStateProvince'] ?? '',
            'postal_code' => $params['RegistrantPostalCode'] ?? '',
            'country_code' => $params['RegistrantCountry'] ?? '',
            'email' => $params['RegistrantEmailAddress'] ?? '',
            // CE may already send +CC.NUMBER; never prefix a second '+'.
            'phone' => '+' . ltrim((string)($params['RegistrantPhone'] ?? ''), '+'),
        ];

        try {
            $client->domains()->register($domain, (int)$params['NumYears'], $nameservers, $registrant, !empty($params['idprotection']));
            return true;
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function renewDomain($params)
    {
        $client = $this->getSdkClient();
        $domain = $params['sld'] . '.' . $params['tld'];

        try {
            $client->domains()->renew($domain, (int)$params['NumYears']);
            return true;
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function getEPPCode($params)
    {
        $client = $this->getSdkClient();
        $domain = $params['sld'] . '.' . $params['tld'];

        try {
            $res = $client->domains()->getAuthCode($domain);
            return $res['auth_code'] ?? '';
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}

<?php
/**
 * Paknic Domain Registrar Module for Blesta
 *
 * Official Registrar Module for Paknic Automation Platform
 * Compatible with Blesta 5.0+ and PHP 7.4 to 8.3+
 */

require_once __DIR__ . '/lib/paknic-sdk/autoload.php';

use DomainRegistrar\SDK\Client;
use DomainRegistrar\SDK\Configuration;
use DomainRegistrar\SDK\Services\DomainService;

class Paknic extends RegistrarModule
{
    private function getClient($module_row): Client
    {
        $fields = $module_row->meta;
        $config = new Configuration(
            $fields->api_key ?? '',
            $fields->api_secret ?? '',
            $fields->api_url ?? Configuration::DEFAULT_BASE_URL
        );
        return new Client($config);
    }

    public function checkAvailability($domain, $module_row_id = null)
    {
        $row = $this->getModuleRow($module_row_id);
        $client = $this->getClient($row);

        try {
            $res = $client->domains()->check($domain);
            return !empty($res['is_available']);
        } catch (Exception $e) {
            $this->Input->setErrors(['api' => ['error' => $e->getMessage()]]);
            return false;
        }
    }

    public function registerDomain($domain, $module_row_id = null, array $vars = [])
    {
        $row = $this->getModuleRow($module_row_id);
        $client = $this->getClient($row);

        $years = (int)($vars['years'] ?? 1);
        // No invented fallback: without nameservers the API applies its own defaults.
        $nameservers = $vars['nameservers'] ?? [];

        $registrant = [
            'first_name' => $vars['first_name'] ?? '',
            'last_name' => $vars['last_name'] ?? '',
            'organization' => $vars['company'] ?? '',
            'address1' => $vars['address1'] ?? '',
            'address2' => $vars['address2'] ?? '',
            'city' => $vars['city'] ?? '',
            'state' => $vars['state'] ?? '',
            'postal_code' => $vars['zip'] ?? '',
            'country_code' => $vars['country'] ?? '',
            'email' => $vars['email'] ?? '',
            'phone' => '+' . ($vars['country_calling_code'] ?? '1') . '.' . preg_replace('/[^0-9]/', '', (string)($vars['phone'] ?? ''))
        ];

        try {
            $res = $client->domains()->register($domain, $years, $nameservers, $registrant, !empty($vars['privacy']));
            return $res;
        } catch (Exception $e) {
            $this->Input->setErrors(['api' => ['error' => $e->getMessage()]]);
            return false;
        }
    }

    public function renewDomain($domain, $module_row_id = null, array $vars = [])
    {
        $row = $this->getModuleRow($module_row_id);
        $client = $this->getClient($row);
        $years = (int)($vars['years'] ?? 1);

        try {
            return $client->domains()->renew($domain, $years);
        } catch (Exception $e) {
            $this->Input->setErrors(['api' => ['error' => $e->getMessage()]]);
            return false;
        }
    }

    public function transferDomain($domain, $module_row_id = null, array $vars = [])
    {
        $row = $this->getModuleRow($module_row_id);
        $client = $this->getClient($row);
        $authCode = $vars['transfer_key'] ?? $vars['auth_code'] ?? '';

        try {
            return $client->domains()->transfer($domain, $authCode);
        } catch (Exception $e) {
            $this->Input->setErrors(['api' => ['error' => $e->getMessage()]]);
            return false;
        }
    }

    public function getDomainInfo($domain, $module_row_id = null)
    {
        $row = $this->getModuleRow($module_row_id);
        $client = $this->getClient($row);

        try {
            return $client->domains()->getDetails($domain);
        } catch (Exception $e) {
            $this->Input->setErrors(['api' => ['error' => $e->getMessage()]]);
            return false;
        }
    }

    public function getNameServers($domain, $module_row_id = null)
    {
        $row = $this->getModuleRow($module_row_id);
        $client = $this->getClient($row);

        try {
            $res = $client->nameservers()->getNameservers($domain);
            return $res['nameservers'] ?? [];
        } catch (Exception $e) {
            $this->Input->setErrors(['api' => ['error' => $e->getMessage()]]);
            return [];
        }
    }

    public function setNameServers($domain, $module_row_id = null, array $nameservers = [])
    {
        $row = $this->getModuleRow($module_row_id);
        $client = $this->getClient($row);

        try {
            return $client->nameservers()->saveNameservers($domain, $nameservers);
        } catch (Exception $e) {
            $this->Input->setErrors(['api' => ['error' => $e->getMessage()]]);
            return false;
        }
    }

    public function getLockStatus($domain, $module_row_id = null)
    {
        $row = $this->getModuleRow($module_row_id);
        $client = $this->getClient($row);

        try {
            $details = $client->domains()->getDetails($domain);
            return DomainService::isLocked($details) ? 'true' : 'false';
        } catch (Exception $e) {
            $this->Input->setErrors(['api' => ['error' => $e->getMessage()]]);
            return 'false';
        }
    }

    public function setLockStatus($domain, $lock_status, $module_row_id = null)
    {
        $row = $this->getModuleRow($module_row_id);
        $client = $this->getClient($row);

        try {
            return $client->domains()->setLock($domain, $lock_status === 'true');
        } catch (Exception $e) {
            $this->Input->setErrors(['api' => ['error' => $e->getMessage()]]);
            return false;
        }
    }

    public function getAuthCode($domain, $module_row_id = null)
    {
        $row = $this->getModuleRow($module_row_id);
        $client = $this->getClient($row);

        try {
            $res = $client->domains()->getAuthCode($domain);
            return $res['auth_code'] ?? '';
        } catch (Exception $e) {
            $this->Input->setErrors(['api' => ['error' => $e->getMessage()]]);
            return '';
        }
    }

    public function getLogo()
    {
        return 'views/default/images/logo.png';
    }
}


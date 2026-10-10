<?php
/**
 * Registers a domain through the Paknic API. Used by the event listener when a domain is created in Plesk.
 */

require_once __DIR__ . '/../lib/paknic-sdk/autoload.php';

use DomainRegistrar\SDK\Client;
use DomainRegistrar\SDK\Configuration;

class Modules_Paknic_Registrar
{
    public static function client()
    {
        return new Client(new Configuration(
            (string)Modules_Paknic_Settings::get('api_key'),
            Modules_Paknic_Settings::apiSecret(),
            (string)Modules_Paknic_Settings::get('api_url', Configuration::DEFAULT_BASE_URL)
        ));
    }

    /**
     * Returns a one-line outcome for the log. Never throws: a failed registration must not break domain creation.
     *
     * @param string $domain       domain created in Plesk
     * @param string $contactEmail the owning customer's email, used as the registrant email when set
     */
    public static function registerIfAvailable($domain, $contactEmail = '')
    {
        $domain = strtolower(trim($domain));
        if (!Modules_Paknic_Settings::flag('auto_register')) {
            return "{$domain}: automatic registration is off.";
        }
        if (Modules_Paknic_Settings::get('api_key') === '' || Modules_Paknic_Settings::apiSecret() === '') {
            return "{$domain}: API key or secret not set.";
        }

        $only = Modules_Paknic_Settings::onlyTlds();
        if ($only) {
            $allowed = false;
            foreach ($only as $tld) {
                if (substr($domain, -strlen($tld)) === $tld) {
                    $allowed = true;
                }
            }
            if (!$allowed) {
                return "{$domain}: TLD not in the allowed list.";
            }
        }

        $registrant = Modules_Paknic_Settings::registrant();
        foreach (Modules_Paknic_Settings::REQUIRED_REGISTRANT as $field) {
            if (trim($registrant[$field]) === '') {
                return "{$domain}: registrant field '{$field}' is not set in the extension settings.";
            }
        }
        if ($contactEmail !== '') {
            $registrant['email'] = $contactEmail;
        }
        $registrant['country_code'] = strtoupper($registrant['country_code']);

        try {
            $client = self::client();
            $check = $client->domains()->check($domain);
            if (empty($check['is_available'])) {
                return "{$domain}: already registered, nothing to do.";
            }
            // WHOIS privacy and DNS management are paid add-ons, bought only when switched on in the settings.
            $result = $client->domains()->register(
                $domain,
                max(1, (int)Modules_Paknic_Settings::get('years', '1')),
                Modules_Paknic_Settings::nameservers(),
                $registrant,
                Modules_Paknic_Settings::flag('whois_privacy'),
                Modules_Paknic_Settings::flag('auto_renew'),
                Modules_Paknic_Settings::flag('dns_management')
            );
            return "{$domain}: registered (status " . (isset($result['status']) ? $result['status'] : '?')
                . ', charged $' . (isset($result['charged_amount']) ? $result['charged_amount'] : '?') . ').';
        } catch (Exception $e) {
            return "{$domain}: registration failed: " . $e->getMessage();
        }
    }
}

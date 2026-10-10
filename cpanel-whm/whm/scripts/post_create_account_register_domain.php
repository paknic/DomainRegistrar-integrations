#!/usr/local/cpanel/3rdparty/bin/php
<?php
/**
 * WHM Standardized Hook script: Whostmgr / Accounts::Create / post
 *
 * When a hosting account is created, registers its domain through Paknic if the domain is still available.
 * Settings: /etc/paknic/whm_config.json (see whm_config.sample.json).
 */

require_once __DIR__ . '/../lib/paknic-sdk/autoload.php';

use DomainRegistrar\SDK\Client;
use DomainRegistrar\SDK\Configuration;

/** WHM reads "<1|0> <message>" from a hook script; a post-stage hook cannot block the account creation. */
function paknic_hook_exit(string $message): void
{
    echo "1 {$message}\n";
    exit(0);
}

function paknic_hook_log(string $message): void
{
    error_log('[Paknic WHM Hook] ' . $message);
}

$context = json_decode((string)file_get_contents('php://stdin'), true);
$data = $context['data'] ?? [];
$domain = strtolower(trim((string)($data['domain'] ?? '')));
$user = (string)($data['user'] ?? '');
$contactEmail = (string)($data['contactemail'] ?? '');

if ($domain === '' || strpos($domain, '.') === false) {
    paknic_hook_exit('No domain to register.');
}

$configFile = getenv('PAKNIC_WHM_CONFIG') ?: '/etc/paknic/whm_config.json';
if (!is_readable($configFile)) {
    paknic_hook_exit('Paknic not configured.');
}

$settings = json_decode((string)file_get_contents($configFile), true);
if (!is_array($settings) || empty($settings['auto_register_on_account_create'])) {
    paknic_hook_exit('Automatic registration is off.');
}
if (empty($settings['api_key']) || empty($settings['api_secret'])) {
    paknic_hook_log("Skipped {$domain}: api_key and api_secret are required in {$configFile}.");
    paknic_hook_exit('Paknic API credentials missing.');
}

// The registrant is the contact set in the config; the domain's contacts can be changed after registration.
$registrantDefaults = $settings['registrant'] ?? [];
$required = ['first_name', 'last_name', 'address1', 'city', 'state', 'postal_code', 'country_code', 'phone'];
$missing = array_values(array_filter($required, static function ($field) use ($registrantDefaults) {
    return trim((string)($registrantDefaults[$field] ?? '')) === '';
}));
if ($missing) {
    paknic_hook_log("Skipped {$domain}: registrant fields missing in {$configFile}: " . implode(', ', $missing) . '.');
    paknic_hook_exit('Registrant details incomplete.');
}

$allowedTlds = array_map('strtolower', $settings['only_tlds'] ?? []);
if ($allowedTlds) {
    $matches = array_filter($allowedTlds, static function ($tld) use ($domain) {
        $tld = '.' . ltrim($tld, '.');
        return substr($domain, -strlen($tld)) === $tld;
    });
    if (!$matches) {
        paknic_hook_exit("{$domain} is not in only_tlds.");
    }
}

$registrant = [
    'first_name' => $registrantDefaults['first_name'],
    'last_name' => $registrantDefaults['last_name'],
    'organization' => $registrantDefaults['organization'] ?? '',
    'address1' => $registrantDefaults['address1'],
    'address2' => $registrantDefaults['address2'] ?? '',
    'city' => $registrantDefaults['city'],
    'state' => $registrantDefaults['state'],
    'postal_code' => $registrantDefaults['postal_code'],
    'country_code' => strtoupper($registrantDefaults['country_code']),
    'email' => $contactEmail !== '' ? $contactEmail : ($registrantDefaults['email'] ?? ''),
    'phone' => $registrantDefaults['phone'],
];

$client = new Client(new Configuration(
    $settings['api_key'],
    $settings['api_secret'],
    $settings['api_url'] ?? Configuration::DEFAULT_BASE_URL
));

try {
    $check = $client->domains()->check($domain);
    if (empty($check['is_available'])) {
        paknic_hook_exit("{$domain} is already registered; nothing to do.");
    }

    // WHOIS privacy and DNS management are paid add-ons: bought only when switched on in the config.
    $result = $client->domains()->register(
        $domain,
        max(1, (int)($settings['years'] ?? 1)),
        $settings['default_nameservers'] ?? [],
        $registrant,
        !empty($settings['whois_privacy']),
        !isset($settings['auto_renew']) || !empty($settings['auto_renew']),
        !empty($settings['dns_management'])
    );
    paknic_hook_log("Registered {$domain} for cPanel user {$user} (status " . ($result['status'] ?? 'unknown') . ', charged $' . ($result['charged_amount'] ?? '?') . ').');
    paknic_hook_exit("Registered {$domain}.");
} catch (Exception $e) {
    paknic_hook_log("Registration of {$domain} failed: " . $e->getMessage());
    paknic_hook_exit("Registration of {$domain} failed; see the error log.");
}

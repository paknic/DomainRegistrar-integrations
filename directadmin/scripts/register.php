<?php
/**
 * Registers a newly created DirectAdmin domain through Paknic when it is still available.
 * Usage (from hooks/domain_create_post.sh): php register.php <domain> [<username>]
 * Every outcome is one line on stdout, which the hook appends to data/paknic.log.
 */

require_once __DIR__ . '/../lib/paknic-sdk/autoload.php';

use DomainRegistrar\SDK\Client;
use DomainRegistrar\SDK\Configuration;

function paknic_log(string $message): void
{
    echo '[' . gmdate('Y-m-d H:i:s') . ' UTC] ' . $message . "\n";
}

$domain = strtolower(trim($argv[1] ?? ''));
$username = preg_replace('/[^a-z0-9_-]/i', '', $argv[2] ?? '');
if (!preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $domain)) {
    exit(0);
}

$configFile = getenv('PAKNIC_DIRECTADMIN_CONFIG') ?: (__DIR__ . '/../data/config.json');
$settings = is_readable($configFile) ? json_decode((string)file_get_contents($configFile), true) : null;
if (!is_array($settings) || ($settings['auto_register'] ?? '0') !== '1') {
    exit(0);
}
if (empty($settings['api_key']) || empty($settings['api_secret'])) {
    paknic_log("{$domain}: API key or secret not set; open the Paknic page in DirectAdmin.");
    exit(0);
}

$onlyTlds = preg_split('/[\s,]+/', (string)($settings['only_tlds'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
if ($onlyTlds) {
    $allowed = false;
    foreach ($onlyTlds as $tld) {
        $tld = '.' . ltrim(strtolower($tld), '.');
        if (substr($domain, -strlen($tld)) === $tld) {
            $allowed = true;
        }
    }
    if (!$allowed) {
        paknic_log("{$domain}: TLD not in the allowed list, skipped.");
        exit(0);
    }
}

$fields = ['first_name', 'last_name', 'organization', 'address1', 'address2', 'city', 'state', 'postal_code', 'country_code', 'email', 'phone'];
$required = ['first_name', 'last_name', 'address1', 'city', 'state', 'postal_code', 'country_code', 'phone'];
$registrant = [];
foreach ($fields as $field) {
    $registrant[$field] = trim((string)($settings['registrant_' . $field] ?? ''));
}
foreach ($required as $field) {
    if ($registrant[$field] === '') {
        paknic_log("{$domain}: registrant field '{$field}' is not set, skipped.");
        exit(0);
    }
}
$registrant['country_code'] = strtoupper($registrant['country_code']);

// The DirectAdmin user's email, when set, is the registrant email.
if ($username !== '') {
    $userConf = "/usr/local/directadmin/data/users/{$username}/user.conf";
    if (is_readable($userConf)) {
        foreach (file($userConf, FILE_IGNORE_NEW_LINES) as $line) {
            if (strpos($line, 'email=') === 0 && trim(substr($line, 6)) !== '') {
                $registrant['email'] = trim(substr($line, 6));
            }
        }
    }
}

$client = new Client(new Configuration(
    $settings['api_key'],
    $settings['api_secret'],
    $settings['api_url'] ?? Configuration::DEFAULT_BASE_URL
));

try {
    $check = $client->domains()->check($domain);
    if (empty($check['is_available'])) {
        paknic_log("{$domain}: already registered, nothing to do.");
        exit(0);
    }
    // WHOIS privacy and DNS management are paid add-ons, bought only when switched on in the settings.
    $result = $client->domains()->register(
        $domain,
        max(1, (int)($settings['years'] ?? 1)),
        preg_split('/[\s,]+/', (string)($settings['nameservers'] ?? ''), -1, PREG_SPLIT_NO_EMPTY),
        $registrant,
        ($settings['whois_privacy'] ?? '0') === '1',
        ($settings['auto_renew'] ?? '1') === '1',
        ($settings['dns_management'] ?? '0') === '1'
    );
    paknic_log("{$domain}: registered for user {$username} (status " . ($result['status'] ?? '?') . ', charged $' . ($result['charged_amount'] ?? '?') . ').');
} catch (Exception $e) {
    paknic_log("{$domain}: registration failed: " . $e->getMessage());
}
exit(0);

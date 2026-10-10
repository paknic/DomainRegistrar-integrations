# Paknic PHP Client SDK (`paknic/php-sdk`)

The official PHP Client SDK for the **Paknic Domain Registrar & Automation API**.

[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.4-8892BF.svg)](https://php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](../LICENSE)

---

## Installation

Install via [Composer](https://getcomposer.org/):

```bash
composer require paknic/php-sdk
```

---

## Quickstart

```php
<?php
require_once 'vendor/autoload.php';

use DomainRegistrar\SDK\Client;
use DomainRegistrar\SDK\Configuration;

// Initialize Configuration
// Using a "pk_test_" key automatically routes calls to the sandbox simulator.
$config = new Configuration(
    'pk_live_your_api_key_id',
    'sk_live_your_api_secret',
    'https://api.paknic.com'
);

$client = new Client($config);

// Check domain availability
$check = $client->domains()->check('example.com');
if ($check['is_available']) {
    echo "example.com is available for registration!\n";
}
```

---

## Available Services

- **`$client->domains()`**: Check availability, register, renew, transfer, get info, lock/unlock, EPP auth code.
- **`$client->nameservers()`**: Manage nameservers and child host records (glue IPv4/IPv6).
- **`$client->contacts()`**: Manage WHOIS contact profiles (registrant, admin, tech, billing).
- **`$client->dns()`**: DNS zone records and DNSSEC delegation signer keys.
- **`$client->addons()`**: Manage WhoisPrivacy and DnsManagement domain add-ons.
- **`$client->account()`**: Check wallet balance and retrieve account-specific TLD wholesale pricing.
- **`$client->webhooks()`**: Manage endpoint subscriptions and verify incoming HMAC-SHA256 signatures (`WebhookService::verifySignature`).

---

## License

This library is open-source software licensed under the [MIT License](LICENSE).

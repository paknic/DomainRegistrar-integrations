# Paknic Domain Registrar Integrations & PHP SDK

[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.4-8892BF.svg)](https://php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Tests](https://img.shields.io/badge/tests-92%20passing-brightgreen.svg)](tests/)
[![API Base](https://img.shields.io/badge/API-api.paknic.com-informational.svg)](https://api.paknic.com/swagger)

Official billing automation modules, web hosting control panel plugins, and PHP client SDK for the **Paknic Domain Registrar & Automation API**.

Automate domain registrations, renewals, inbound/outbound transfers, DNS zone management, WHOIS contact updates, DNSSEC, and lifecycle synchronization directly within your hosting and billing platforms.

---

## Supported Platforms & Components

| Platform / Component | Type | Directory | Compatibility | Status |
| :--- | :--- | :--- | :--- | :--- |
| **[WHMCS](whmcs/)** | Registrar Module | `whmcs/` | WHMCS 8.0 – 8.11+ (PHP 7.4 – 8.3) | **Production** |
| **[HostBill](hostbill/)** | Registrar Module | `hostbill/` | HostBill 2023+ (PHP 7.4 – 8.2) | **Production** |
| **[cPanel & WHM](cpanel-whm/)** | Account Hook | `cpanel-whm/` | cPanel/WHM v110+ (CentOS / AlmaLinux / CloudLinux) | **Production** |
| **[DirectAdmin](directadmin/)** | Panel Plugin | `directadmin/` | DirectAdmin 1.62+ (Linux) | **Production** |
| **[Plesk Obsidian](plesk/)** | Panel Extension | `plesk/` | Plesk Obsidian 18.0+ (Linux) | **Production** |
| **[Blesta](blesta/)** | Registrar Module | `blesta/` | Blesta 5.x | *Preview* |
| **[ClientExec](clientexec/)** | Registrar Plugin | `clientexec/` | ClientExec 6.x | *Preview* |
| **[PHP Client SDK](sdk-php/)** | Client Library | `sdk-php/` | PHP 7.4+, 8.0, 8.1, 8.2, 8.3 | **Production** |

---

## Prerequisites & API Credentials

To use any module or the PHP SDK:
1. Create a customer account at [Paknic](https://www.paknic.com).
2. Complete your account identity verification under **My Account > Identity Verification (KYC)**.
3. Once approved, navigate to **My Account > Developer & API > API Keys & Auth**.
4. Generate an API Key:
   - **Production Key**: Prefix `pk_live_...` with secret `sk_live_...`. Charges your prepaid wallet balance and registers real domains with registries.
   - **Sandbox Key**: Prefix `pk_test_...` with secret `sk_test_...`. Runs against our registry simulator with zero charges and simulated responses.
5. **IP Allowlist**: If your server uses a static outbound IP, add it to your key's IP allowlist for security.
6. **API Base URL**: `https://api.paknic.com` (host only, without `/api/v1`).

---

## Installation & Distribution Packages

Prebuilt, self-contained distribution archives for each platform (with the PHP SDK already bundled in `lib/paknic-sdk/`) are available from the **[Releases](https://github.com/paknic/DomainRegistrar-integrations/releases)** tab or directly within your account at **My Account > Developer & API > Integrations & Plugins**:

- `paknic-whmcs.zip`
- `paknic-hostbill.zip`
- `paknic-cpanel-whm.zip`
- `paknic-directadmin.tar.gz` & `paknic-directadmin.zip`
- `paknic-plesk.zip`
- `paknic-blesta.zip`
- `paknic-clientexec.zip`
- `paknic-sdk-php.zip`

---

## Platform Quickstart Guides

### 1. WHMCS
1. Download `paknic-whmcs.zip` from [Releases](https://github.com/paknic/DomainRegistrar-integrations/releases) or the member portal.
2. Extract the archive into your WHMCS root directory (`whmcs/modules/registrars/paknic/`).
3. In WHMCS Admin, go to **Configuration > System Settings > Domain Registrars**.
4. Activate **Paknic Domain Registrar Automation** and enter your **API Key ID**, **API Secret**, and **API Base URL**.
5. Set up price synchronization via cron or Registrar TLD Sync:
   ```bash
   # Daily at 03:15 with 15% markup ending in .99
   15 3 * * * php -q /path/to/whmcs/modules/registrars/paknic/sync.php --markup=15% --round=.99 >/dev/null 2>&1
   ```

   The same run also reads the TLDs that are winding down (`GET /api/v1/tlds/lifecycle`: sales paused, sunsetting,
   migrating, retired). For those set up in WHMCS, it marks closed prices as not offered (-1): registration and
   transfer once registration closes, renewal after the renewal cut-off. Each is listed with its dates and logged.

### 2. HostBill
1. Extract `paknic-hostbill.zip` into your HostBill installation root (`includes/modules/Domain/paknic/`).
2. In HostBill Admin, go to **Settings > Modules > Domain Modules** and activate **Paknic**.
3. Input your API credentials and click **Test Connection** to verify your wallet balance.
4. Assign Paknic as the registrar for your desired TLDs under **Settings > Products & Services > Domains**.

### 3. cPanel & WHM
Automates domain registration via WHM account creation hooks:
1. Copy the `cpanel-whm/whm/` directory to `/usr/local/paknic/whm/`.
2. Create your configuration file:
   ```bash
   mkdir -p /etc/paknic
   cp /usr/local/paknic/whm/whm_config.sample.json /etc/paknic/whm_config.json
   chmod 600 /etc/paknic/whm_config.json
   ```
3. Set your API credentials, default nameservers, and registrant contact details in `/etc/paknic/whm_config.json`.
4. Register the hook in WHM:
   ```bash
   chmod 755 /usr/local/paknic/whm/scripts/post_create_account_register_domain.php
   /usr/local/cpanel/bin/manage_hooks add script \
     /usr/local/paknic/whm/scripts/post_create_account_register_domain.php \
     --manual --category Whostmgr --event Accounts::Create --stage post
   ```

### 4. DirectAdmin
1. In DirectAdmin, go to **Admin > Plugin Manager > Add Plugin**.
2. Upload `paknic-directadmin.tar.gz`.
3. Navigate to **Admin > Extra Features > Paknic Domain Registration**.
4. Configure your API key, secret, nameservers, and default registrant details.
5. Enable **Register new domains automatically**.

### 5. Plesk Obsidian
1. In Plesk, go to **Extensions > My Extensions > Upload Extension**.
2. Upload `paknic-plesk.zip`.
3. Open the Paknic extension settings, input your API credentials, nameservers, and registrant details, then enable automatic registration.

---

## Official PHP Client SDK (`sdk-php`)

The PHP SDK provides a modern, strictly-typed client for building custom SaaS applications, billing integrations, or internal scripts:

### Installation via Composer
```bash
composer require paknic/php-sdk
```

### Usage Example
```php
<?php
require_once 'vendor/autoload.php';

use DomainRegistrar\SDK\Client;
use DomainRegistrar\SDK\Configuration;

// Initialize Client (pk_test_ enables sandbox simulator automatically)
$config = new Configuration(
    'pk_live_your_api_key_id',
    'sk_live_your_api_secret',
    'https://api.paknic.com'
);
$client = new Client($config);

// 1. Check Availability
$check = $client->domains()->check('example-startup.com');
if ($check['is_available']) {
    echo "Domain is available! Wholesale cost: $" . $check['price'] . "\n";

    // 2. Register Domain
    $result = $client->domains()->register(
        'example-startup.com',
        1, // years
        ['ns1.example.com', 'ns2.example.com'],
        [
            'first_name'   => 'Alex',
            'last_name'    => 'Mercer',
            'organization' => 'Mercer Labs',
            'address1'     => '100 Tech Blvd',
            'city'         => 'Austin',
            'state'        => 'TX',
            'postal_code'  => '78701',
            'country_code' => 'US',
            'email'        => 'alex@mercerlabs.com',
            'phone'        => '+1.5125550199'
        ],
        true, // WhoisPrivacy
        true  // AutoRenew
    );

    echo "Registered! Domain ID: " . $result['domain_id'] . "\n";
}
```

---

## Running the Automated Test Suite

A standalone mock API server and unit test suite is included to validate all modules without network access or live credentials:

```bash
php tests/run_all_tests.php
```

Suite covers:
- PHP Client SDK unit and mock contract tests
- WHMCS registrar methods, hooks, DNS editor, and PriceSync math
- HostBill class handlers, contact updates, and sync mappings
- cPanel/WHM hook execution and error log formatting
- DirectAdmin register script and permitted TLD filtering
- Plesk extension settings parser and event listener
- Blesta and ClientExec preview handlers

---

## Security & Bug Reports

If you discover a security vulnerability within this repository or the Paknic API, please report it privately to **security@paknic.com**. Please do not open public GitHub issues for security vulnerabilities.

## License

This project is open-source software licensed under the [MIT License](LICENSE).

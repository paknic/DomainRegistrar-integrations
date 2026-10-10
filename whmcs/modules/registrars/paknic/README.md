# Paknic Registrar Module for WHMCS

Domain registrar module for WHMCS 8.0 – 8.11+ (PHP 7.4 – 8.3).

## Features
- Registration, renewal and transfer (1–10 years), with transfer status sync
- Nameservers, child nameservers (glue records, IPv4 or IPv6)
- Registrar transfer lock (get and set)
- EPP auth code
- WHOIS contacts: registrant, admin, technical, billing
- ID Protection → Paknic **WhoisPrivacy** add-on (paid; bought from your wallet when turned on)
- DNS Management → Paknic **DnsManagement** add-on (paid; bought from your wallet on first save when the domain has DNS Management in WHMCS)
- Domain sync (status and expiry) and Registrar TLD Sync (price import)
- Price sync cron with markup (`sync.php`)
- Admin dashboard widget: wallet balance and low-balance warning

Paid add-ons: ID Protection and DNS Management are charged to your Paknic wallet at their add-on prices when they are ordered with a registration or first used. Turning ID Protection off also turns off the add-on's auto-renewal.

## Installation
1. Build the plugin (bundles the SDK): `pwsh Integrations/build.ps1`, then unzip `dist/paknic-whmcs.zip` into your WHMCS root.
   Or copy `modules/registrars/paknic` (after running the build) to `/path/to/whmcs/modules/registrars/`.
2. In WHMCS Admin go to **Configuration > System Settings > Domain Registrars**, find **Paknic Domain Registrar Automation** and click **Activate**.
3. Enter the **API Key ID** and **API Secret**. A `pk_test_` key works against the sandbox: nothing is registered and nothing is charged. A `pk_live_` key is live.
4. Leave **API Base URL** as `https://api.paknic.com`. Enter the host only, without `/api/v1`.
5. If the key has an IP allow-list, add your WHMCS server's outbound IP to it.
6. Click **Save Changes**.

## Prices
- **Registrar TLD Sync** (WHMCS **Utilities > Registrar TLD Sync**) imports the prices your key is charged, and lets you set a margin in WHMCS.
- **Cron alternative:** `sync.php` updates the prices of TLDs already set up in WHMCS whose automatic registrar is Paknic:
  ```bash
  # daily at 03:15; dry run first to check the numbers
  php -q /path/to/whmcs/modules/registrars/paknic/sync.php --markup=15% --round=.99 --dry-run
  15 3 * * * php -q /path/to/whmcs/modules/registrars/paknic/sync.php --markup=15% --round=.99 >/dev/null 2>&1
  ```
  - `--markup` takes a percent (`15%`) or a fixed amount (`2.50`).
  - `--round=.99` makes prices end in .99, rounding up.
  - `--all` also updates TLDs assigned to other registrars.
  - Prices come from the API in USD, and WHMCS needs a USD currency so other currencies can be converted with their WHMCS exchange rates.

Domain status and transfer sync run from the standard WHMCS cron (**Domain Sync**); no extra cron is needed.

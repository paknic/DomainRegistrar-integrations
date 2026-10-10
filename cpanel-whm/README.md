# cPanel & WHM: Paknic automatic domain registration

A WHM hook that registers a new hosting account's domain through Paknic, when the domain is still available.

## How it works
- The hook runs after WHM creates an account (**Whostmgr / Accounts::Create / post**).
- It checks the account's domain. If the domain is available, it registers it with the registrant set in the config and the account's contact email.
- It never blocks the account creation. Results and errors go to the cPanel error log (`/usr/local/cpanel/logs/error_log`), prefixed `[Paknic WHM Hook]`.
- Registration is charged to your Paknic wallet. WHOIS privacy (`whois_privacy`) and DNS management (`dns_management`) are paid add-ons, so they are off unless you turn them on.
- A `pk_test_` key runs against the sandbox: nothing is registered or charged.

## Installation
1. Build the plugin (bundles the SDK): `pwsh Integrations/build.ps1`. Copy the `whm/` folder to the server, e.g. `/usr/local/paknic/whm/`.
2. Create the configuration:
   ```bash
   mkdir -p /etc/paknic
   cp /usr/local/paknic/whm/whm_config.sample.json /etc/paknic/whm_config.json
   chmod 600 /etc/paknic/whm_config.json
   ```
   Set `api_key`, `api_secret`, the `registrant` block and `default_nameservers`. Set `auto_register_on_account_create` to `true` when you're ready. `only_tlds` limits which domains are registered; leave it empty for all.
3. Make the script executable and register the hook:
   ```bash
   chmod 755 /usr/local/paknic/whm/scripts/post_create_account_register_domain.php
   /usr/local/cpanel/bin/manage_hooks add script \
     /usr/local/paknic/whm/scripts/post_create_account_register_domain.php \
     --manual --category Whostmgr --event Accounts::Create --stage post
   ```
4. If the API key has an IP allow-list, add the server's outbound IP.

To remove: `/usr/local/cpanel/bin/manage_hooks delete script /usr/local/paknic/whm/scripts/post_create_account_register_domain.php --manual --category Whostmgr --event Accounts::Create --stage post`.

## Not included
There is no cPanel user interface. Customers manage nameservers, DNS and locks on the Paknic site or through your billing system's module.

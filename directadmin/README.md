# Paknic Domain Registration plugin for DirectAdmin

Registers a newly created DirectAdmin domain through Paknic, when it is still available.

## How it works
- DirectAdmin runs `hooks/domain_create_post.sh` after a domain is created. The hook starts `scripts/register.php` in the background and always exits 0, so it never fails or slows domain creation.
- `register.php` registers the domain if **Register new domains automatically** is on, the TLD is allowed, and the domain is available. It uses the registrant from the settings, the DirectAdmin user's email and your nameservers.
- Each outcome is logged to `data/paknic.log`.
- Registration is charged to your Paknic wallet. WHOIS privacy and DNS management are paid add-ons and are off by default.
- A `pk_test_` key uses the sandbox: nothing is registered or charged.

## Installation
1. Build it: `pwsh Integrations/build.ps1`. This bundles the SDK into `lib/` and writes `dist/paknic-directadmin.tar.gz`, with `plugin.conf` at the archive root and the scripts marked executable.
2. In DirectAdmin go to **Admin > Plugin Manager > Add Plugin**, upload the archive and install. `scripts/install.sh` sets permissions: `data/` is readable only by diradmin and root.
3. Open **Admin > Extra Features > Paknic Domain Registration** and enter:
   - the API key and secret, and the API URL `https://api.paknic.com`;
   - the nameservers and every registrant field.
4. Save. The page tests the connection and shows your wallet balance.
5. Turn on **Register new domains automatically** when you're ready.
6. If the key has an IP allow-list, add the server's outbound IP.

## Not included
Renewals, transfers, DNS and lock management. Use the Paknic site or your billing system's module for those.

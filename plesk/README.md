# Paknic Domain Registration for Plesk Obsidian

A Plesk extension that registers a newly created domain through Paknic, when it is still available.

## How it works
- The extension listens for Plesk's **domain created** event (`domain_create`). If automatic registration is on and the domain is available, it registers the domain through Paknic, using:
  - the registrant from the extension settings,
  - the owning customer's email,
  - your nameservers.
- It never blocks domain creation. Each outcome goes to the Plesk extension log with the prefix `[Paknic]`.
- Registration is charged to your Paknic wallet. WHOIS privacy and DNS management are paid add-ons and are off by default.
- A `pk_test_` key runs against the sandbox: nothing is registered or charged.

## Installation
1. Build it: `pwsh Integrations/build.ps1`. This produces `dist/paknic-plesk.zip` with the SDK bundled.
2. In Plesk go to **Extensions > My Extensions > Upload Extension** and upload the zip.
3. Open the extension and fill in:
   - the API key and secret, and the API URL `https://api.paknic.com`;
   - the nameservers;
   - every registrant field.
4. Save. The page tests the connection and shows your wallet balance.
5. Turn on **Register new domains automatically** when you're ready.
6. If the key has an IP allow-list, add the Plesk server's outbound IP.

## Not included
Renewals, transfers, DNS and lock management are not handled in Plesk. Use the Paknic site or your billing system's module for those.

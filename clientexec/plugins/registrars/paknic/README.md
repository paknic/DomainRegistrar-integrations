# Paknic Registrar Plugin for Clientexec: **Preview, not production-ready**

This plugin is a starting point, not a complete Clientexec registrar plugin.

## What works
Through the bundled SDK:
- availability check
- register (2 nameservers)
- renew
- EPP code

The `features` list advertises only these.

## What's missing before live use
- A check that the `RegistrarPlugin` include path and the `checkDomain` return format match your Clientexec version.
- Transfers, contacts, nameserver changes, lock, WHOIS privacy and DNS (both paid Paknic add-ons), child nameservers, and domain sync (`getGeneralInfo`, `fetchDomains`).

Settings: API Key, API Secret, API URL (`https://api.paknic.com`, host only). A `pk_test_` key uses the sandbox. Run `pwsh Integrations/build.ps1` first; it bundles the SDK into `lib/`.

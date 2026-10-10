# Paknic Registrar Module for Blesta: **Preview, not production-ready**

This module is a starting point. It is not a complete Blesta registrar module and should not be installed on a live Blesta.

## What works
The methods below call the Paknic API correctly through the bundled SDK:
- availability check, register, renew, transfer
- domain info
- get/set nameservers
- get/set the transfer lock
- EPP code

## What's missing before it can be installed
- `config.json`, language files, and module-row management (add/edit/manage row views, field validation). Blesta needs these to install a module and store the API key and secret.
- Method names that match Blesta 5's `RegistrarModule` interface, e.g. `getDomainIsLocked`, `lockDomain`/`unlockDomain`, `getDomainNameServers`/`setDomainNameservers`, `getDomainContacts`/`setDomainContacts`, `getExpirationDate`.
- Contacts, WHOIS privacy (a paid Paknic add-on), DNS management (a paid Paknic add-on), and domain sync.

Settings, once the module rows exist: API Key ID, API Secret, API Base URL (`https://api.paknic.com`, host only). A `pk_test_` key uses the sandbox.

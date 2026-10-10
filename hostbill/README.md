# Paknic Registrar Module for HostBill

Domain registrar module for HostBill.

## Features
- Register, transfer, renew (1–10 years)
- Nameservers; child nameservers (register, modify, delete; IPv4 or IPv6)
- Contacts: registrant, admin, tech, billing
- EPP code
- Registrar transfer lock
- ID protection → Paknic **WhoisPrivacy** add-on (paid from your wallet when turned on)
- DNS records → Paknic **DnsManagement** add-on (paid from your wallet on first save when the domain has DNS management)
- Domain synchronisation: status, expiry, nameservers, lock, ID protection, auto-renew

## Installation
1. Build the plugin (bundles the SDK): `pwsh Integrations/build.ps1`, then unzip `dist/paknic-hostbill.zip` into the HostBill root. This creates `includes/modules/Domain/paknic/`.
2. In HostBill go to **Settings > Modules > Domain Modules**, find **Paknic** and activate it.
3. Enter the **API Key ID**, **API Secret** and **API Base URL** (`https://api.paknic.com`, host only). A `pk_test_` key uses the sandbox: nothing is registered or charged.
4. Use **Test Connection**. It reads your wallet balance.
5. Assign Paknic as the registrar for your TLDs under **Settings > Products & Services > Domains**.

## Check on a real install before going live
HostBill publishes no reference for the registrar-module interface. This module follows two sources:
- existing open-source HostBill registrar modules: argument-less methods, `$this->options`/`$this->period`/`$this->details`/`$this->domain_contacts`, and `addInfo`/`addError`;
- HostBill's [Admin API](https://api2.hostbillapp.com/) for data shapes: contact roles and fields from `domainGetContacts`/`domainSetContacts`, and domain fields (`expires`, `status`, `reglock`, `idprotection`, `autorenew`, `nameservers`) from `getDomainDetails`.

Confirm on your HostBill version:
- the lock, ID protection and DNS method names (`getRegistrarLock`/`updateRegistrarLock`, `updateIDProtection`, `getDNSmanagement`/`updateDNSManagement`);
- the array returned by `synchInfo`.

**Not included:** DNSSEC. HostBill manages it through a module widget (`domainGetDNSSEC`/`domainSetDNSSEC`) with no published interface. The Paknic API and SDK support DNSSEC, so it can be added once the widget contract is known.

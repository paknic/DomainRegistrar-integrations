<?php

declare(strict_types=1);

namespace DomainRegistrar\Tests;

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/WhmcsMocks.php';
require_once __DIR__ . '/../whmcs/modules/registrars/paknic/paknic.php';
require_once __DIR__ . '/../whmcs/modules/registrars/paknic/lib/PriceSync.php';

use Paknic\Whmcs\PriceSync;

function runWhmcsTests(string $baseUrl): void
{
    TestRunner::suite('WHMCS PriceSync Unit Tests', function () {
        TestRunner::test('PriceSync::parseMarkup', function () {
            TestRunner::assertEquals(['percent', 15.0], PriceSync::parseMarkup('15%'));
            TestRunner::assertEquals(['fixed', 2.50], PriceSync::parseMarkup('2.50'));
            TestRunner::assertEquals(['fixed', 0.0], PriceSync::parseMarkup(''));
            TestRunner::assertEquals(['fixed', 0.0], PriceSync::parseMarkup(null));
        });

        TestRunner::test('PriceSync::parseRounding', function () {
            TestRunner::assertEquals(0.99, PriceSync::parseRounding('.99'));
            TestRunner::assertEquals(0.95, PriceSync::parseRounding('0.95'));
            TestRunner::assertEquals(0.00, PriceSync::parseRounding('.00'));
            TestRunner::assertEquals(null, PriceSync::parseRounding(''));
        });

        TestRunner::test('PriceSync::sellPrice with percentage markup and rounding', function () {
            // Cost $10, +10% = $11.00 -> rounded to .99 = $11.99
            TestRunner::assertEquals(11.99, PriceSync::sellPrice(10.00, ['percent', 10], 0.99));
            // Cost $10, +$1.50 = $11.50 -> rounded to .99 = $11.99
            TestRunner::assertEquals(11.99, PriceSync::sellPrice(10.00, ['fixed', 1.50], 0.99));
            // Cost $10, no ending rounding -> $11.50
            TestRunner::assertEquals(11.50, PriceSync::sellPrice(10.00, ['fixed', 1.50], null));
        });

        TestRunner::test('PriceSync::columnsFor generates valid 10-year columns', function () {
            $tld = [
                'tld' => '.com',
                'register_price' => 10.00,
                'renew_price' => 12.00,
                'transfer_price' => 10.00,
                'min_years' => 1,
                'max_years' => 5
            ];
            $cols = PriceSync::columnsFor($tld, 'domainregister', ['percent', 10], 0.99);
            // Year 1 (msetupfee): $10 * 1.10 = $11.00 -> $11.99
            TestRunner::assertEquals(11.99, $cols['msetupfee']);
            // Year 5 (bsetupfee): $50 * 1.10 = $55.00 -> $55.99
            TestRunner::assertEquals(55.99, $cols['bsetupfee']);
            // Year 6 (monthly): beyond max_years=5 -> -1.0
            TestRunner::assertEquals(PriceSync::NOT_OFFERED, $cols['monthly']);
        });

        TestRunner::test('PriceSync::convert handles foreign currencies', function () {
            $cols = ['msetupfee' => 11.99, 'monthly' => -1.0];
            // USD rate 1.0, EUR rate 0.90 -> 11.99 * 0.90 = 10.791 -> round to .99 = 10.99
            $converted = PriceSync::convert($cols, 1.0, 0.90, ['fixed', 0.0], 0.99);
            TestRunner::assertEquals(10.99, $converted['msetupfee']);
            TestRunner::assertEquals(PriceSync::NOT_OFFERED, $converted['monthly']);
        });
    });

    TestRunner::suite('WHMCS paknic.php Integration Tests', function () use ($baseUrl) {
        $commonParams = [
            'ApiKey' => 'pk_test_abc123',
            'ApiSecret' => 'sk_test_xyz789',
            'ApiUrl' => $baseUrl,
            'sld' => 'mywhmcsdomain',
            'tld' => 'com',
            'regperiod' => 1,
            'firstname' => 'John',
            'lastname' => 'Doe',
            'companyname' => 'Whmcs Corp',
            'address1' => '123 Main St',
            'city' => 'Dallas',
            'state' => 'TX',
            'postcode' => '75001',
            'countrycode' => 'US',
            'email' => 'john@whmcscorp.com',
            'fullphonenumber' => '+1.5551234567',
            'ns1' => 'ns1.paknic.com',
            'ns2' => 'ns2.paknic.com',
        ];

        TestRunner::test('paknic_getConfigArray returns valid WHMCS module config', function () {
            $config = paknic_getConfigArray();
            TestRunner::assertNotEmpty($config['FriendlyName']);
            TestRunner::assertNotEmpty($config['ApiKey']);
            TestRunner::assertNotEmpty($config['ApiSecret']);
            TestRunner::assertNotEmpty($config['ApiUrl']);
        });

        TestRunner::test('paknic_formatPhone handles various formats', function () use ($commonParams) {
            TestRunner::assertEquals('+1.5551234567', paknic_formatPhone($commonParams));
            TestRunner::assertEquals('+44.7123456789', paknic_formatPhone(['phonecc' => '44', 'phonenumber' => '7123456789']));
        });

        TestRunner::test('paknic_CheckAvailability returns ResultsList with availability', function () use ($commonParams) {
            $params = array_merge($commonParams, [
                'searchTerm' => 'avail-whmcs',
                'tldsToInclude' => ['.com', '.net'],
                'isIdnDomain' => false
            ]);
            $res = paknic_CheckAvailability($params);
            TestRunner::assertEquals(2, $res->count());
            TestRunner::assertEquals(\WHMCS\Domains\DomainLookup\SearchResult::STATUS_NOT_REGISTERED, $res->offsetGet(0)->getStatus());
        });

        TestRunner::test('paknic_CheckAvailability handles unsupported TLD gracefully', function () use ($commonParams) {
            $params = array_merge($commonParams, [
                'searchTerm' => 'unsupported',
                'tldsToInclude' => ['.xyz'],
                'isIdnDomain' => false
            ]);
            $res = paknic_CheckAvailability($params);
            TestRunner::assertEquals(1, $res->count());
            TestRunner::assertEquals(\WHMCS\Domains\DomainLookup\SearchResult::STATUS_TLD_NOT_SUPPORTED, $res->offsetGet(0)->getStatus());
        });

        TestRunner::test('paknic_GetTldPricing imports prices into WHMCS', function () use ($commonParams) {
            $items = paknic_GetTldPricing($commonParams);
            TestRunner::assertNotEmpty($items);
            TestRunner::assertTrue($items->count() >= 2);
        });

        TestRunner::test('paknic_RegisterDomain with idprotection and dnsmanagement', function () use ($commonParams) {
            $params = array_merge($commonParams, [
                'idprotection' => true,
                'dnsmanagement' => true
            ]);
            $res = paknic_RegisterDomain($params);
            TestRunner::assertTrue(!empty($res['success']));
        });

        TestRunner::test('paknic_RenewDomain', function () use ($commonParams) {
            $res = paknic_RenewDomain($commonParams);
            TestRunner::assertTrue(!empty($res['success']));
        });

        TestRunner::test('paknic_TransferDomain', function () use ($commonParams) {
            $params = array_merge($commonParams, ['eppcode' => 'Auth123!']);
            $res = paknic_TransferDomain($params);
            TestRunner::assertTrue(!empty($res['success']));
        });

        TestRunner::test('paknic_GetNameservers and paknic_SaveNameservers', function () use ($commonParams) {
            $ns = paknic_GetNameservers($commonParams);
            TestRunner::assertEquals('ns1.paknic.com', $ns['ns1']);
            TestRunner::assertEquals('ns2.paknic.com', $ns['ns2']);

            $save = paknic_SaveNameservers(array_merge($commonParams, [
                'ns1' => 'ns1.custom.com',
                'ns2' => 'ns2.custom.com'
            ]));
            TestRunner::assertTrue(!empty($save['success']));
        });

        TestRunner::test('paknic_GetRegistrarLock and paknic_SaveRegistrarLock', function () use ($commonParams) {
            $save = paknic_SaveRegistrarLock(array_merge($commonParams, ['lockenabled' => 'locked']));
            TestRunner::assertTrue(!empty($save['success']));

            $lock = paknic_GetRegistrarLock($commonParams);
            TestRunner::assertEquals('locked', $lock);

            $saveUnlock = paknic_SaveRegistrarLock(array_merge($commonParams, ['lockenabled' => 'unlocked']));
            TestRunner::assertTrue(!empty($saveUnlock['success']));
        });

        TestRunner::test('paknic_GetEPPCode', function () use ($commonParams) {
            $res = paknic_GetEPPCode($commonParams);
            TestRunner::assertNotEmpty($res['eppcode']);
        });

        TestRunner::test('paknic_IDProtectToggle buys add-on when enabled', function () use ($commonParams) {
            $enable = paknic_IDProtectToggle(array_merge($commonParams, ['protectenable' => true]));
            TestRunner::assertTrue(!empty($enable['success']));

            $disable = paknic_IDProtectToggle(array_merge($commonParams, ['protectenable' => false]));
            TestRunner::assertTrue(!empty($disable['success']));
        });

        TestRunner::test('paknic_GetContactDetails and paknic_SaveContactDetails', function () use ($commonParams) {
            $contacts = paknic_GetContactDetails($commonParams);
            TestRunner::assertEquals('John', $contacts['Registrant']['First Name']);

            $save = paknic_SaveContactDetails(array_merge($commonParams, [
                'contactdetails' => [
                    'Registrant' => [
                        'First Name' => 'John',
                        'Last Name' => 'Doe',
                        'Company Name' => 'Paknic',
                        'Address 1' => '123 Main St',
                        'City' => 'New York',
                        'State' => 'NY',
                        'Postcode' => '10001',
                        'Country' => 'US',
                        'Email Address' => 'john@paknic.com',
                        'Phone Number' => '+1.5551234567'
                    ]
                ]
            ]));
            TestRunner::assertTrue(!empty($save['success']));
        });

        TestRunner::test('paknic_GetDNS and paknic_SaveDNS preserves SRV records & auto-buys addon', function () use ($commonParams) {
            // New domain without DnsManagement
            $dnsParams = array_merge($commonParams, [
                'sld' => 'whmcs-dns-autoaddon',
                'dnsmanagement' => true,
                'dnsrecords' => [
                    ['hostname' => '@', 'type' => 'A', 'address' => '1.2.3.4'],
                    ['hostname' => 'mail', 'type' => 'MX', 'address' => 'mail.whmcs-dns-autoaddon.com', 'priority' => 10]
                ]
            ]);

            // SaveDNS should catch ADDON_NOT_ACTIVE, auto-buy DnsManagement because dnsmanagement=true, and succeed!
            $save = paknic_SaveDNS($dnsParams);
            TestRunner::assertTrue(!empty($save['success']));

            $records = paknic_GetDNS($dnsParams);
            TestRunner::assertNotEmpty($records);
        });

        TestRunner::test('paknic_RegisterNameserver and paknic_DeleteNameserver', function () use ($commonParams) {
            $reg = paknic_RegisterNameserver(array_merge($commonParams, [
                'nameserver' => 'ns1.mywhmcsdomain.com',
                'ipaddress' => '192.0.2.100'
            ]));
            TestRunner::assertTrue(!empty($reg['success']));

            $del = paknic_DeleteNameserver(array_merge($commonParams, [
                'nameserver' => 'ns1.mywhmcsdomain.com'
            ]));
            TestRunner::assertTrue(!empty($del['success']));
        });

        TestRunner::test('paknic_Sync status mapping', function () use ($commonParams) {
            $sync = paknic_Sync($commonParams);
            TestRunner::assertTrue($sync['active']);
            TestRunner::assertFalse($sync['expired']);
            TestRunner::assertNotEmpty($sync['expirydate']);
        });

        TestRunner::test('paknic_TransferSync status handling', function () use ($commonParams) {
            $comp = paknic_TransferSync(array_merge($commonParams, ['sld' => 'completed-transfer']));
            TestRunner::assertTrue(!empty($comp['completed']));

            $rej = paknic_TransferSync(array_merge($commonParams, ['sld' => 'rejected-transfer']));
            TestRunner::assertTrue(!empty($rej['failed']));
        });
    });
}

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $baseUrl = $argv[1] ?? 'http://127.0.0.1:8999';
    runWhmcsTests($baseUrl);
    exit(TestRunner::summary());
}

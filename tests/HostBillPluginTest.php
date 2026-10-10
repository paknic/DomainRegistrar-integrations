<?php

declare(strict_types=1);

namespace DomainRegistrar\Tests;

require_once __DIR__ . '/TestRunner.php';

require_once __DIR__ . '/PlatformMocks.php';

require_once __DIR__ . '/../hostbill/includes/modules/Domain/paknic/class.paknic.php';

function runHostBillTests(string $baseUrl): void
{
    TestRunner::suite('HostBill class.paknic.php Integration Tests', function () use ($baseUrl) {
        $hb = new \paknic();
        $hb->configuration = [
            'api_key' => ['value' => 'pk_test_abc123'],
            'api_secret' => ['value' => 'sk_test_xyz789'],
            'api_url' => ['value' => $baseUrl],
        ];
        $hb->options = [
            'sld' => 'hostbill-domain',
            'tld' => 'com',
            'ns1' => 'ns1.paknic.com',
            'ns2' => 'ns2.paknic.com',
            'idprotection' => 1,
            'dnsmanagement' => 1,
        ];
        $hb->period = 1;
        $hb->domain_contacts = [
            'registrant' => [
                'firstname' => 'Host',
                'lastname' => 'Bill',
                'companyname' => 'HostBill Corp',
                'address1' => '123 Billing Way',
                'city' => 'Chicago',
                'state' => 'IL',
                'postcode' => '60601',
                'country' => 'US',
                'email' => 'hb@example.com',
                'phonenumber' => '+1.5559998888'
            ]
        ];

        TestRunner::test('testConnection verifies API connectivity', function () use ($hb) {
            TestRunner::assertTrue($hb->testConnection());
        });

        TestRunner::test('Register registers domain with contacts and add-ons', function () use ($hb) {
            $ok = $hb->Register();
            TestRunner::assertTrue($ok);
            TestRunner::assertNotEmpty($hb->infos);
        });

        TestRunner::test('Renew renews domain term', function () use ($hb) {
            $ok = $hb->Renew();
            TestRunner::assertTrue($ok);
        });

        TestRunner::test('Transfer initiates transfer', function () use ($hb) {
            $hb->options['epp_code'] = 'SecretEPP!';
            $ok = $hb->Transfer();
            TestRunner::assertTrue($ok);
        });

        TestRunner::test('getNameServers and updateNameServers', function () use ($hb) {
            $ns = $hb->getNameServers();
            TestRunner::assertNotEmpty($ns);
            TestRunner::assertEquals('ns1.paknic.com', $ns['ns1']);

            $hb->options['ns1'] = 'ns1.newhb.com';
            $hb->options['ns2'] = 'ns2.newhb.com';
            $ok = $hb->updateNameServers();
            TestRunner::assertTrue($ok);
        });

        TestRunner::test('getContactInfo and updateContactInfo', function () use ($hb) {
            $contacts = $hb->getContactInfo();
            TestRunner::assertNotEmpty($contacts['registrant']);
            TestRunner::assertEquals('John', $contacts['registrant']['firstname']);

            $hb->options['registrant'] = [
                'firstname' => 'Updated',
                'lastname' => 'Contact',
                'companyname' => 'HostBill',
                'address1' => '123 Main',
                'city' => 'NY',
                'state' => 'NY',
                'postcode' => '10001',
                'country' => 'US',
                'email' => 'up@example.com',
                'phonenumber' => '+1.5551234567'
            ];
            $ok = $hb->updateContactInfo();
            TestRunner::assertTrue($ok);
        });

        TestRunner::test('getEppCode retrieves auth code', function () use ($hb) {
            $code = $hb->getEppCode();
            TestRunner::assertNotEmpty($code);
        });

        TestRunner::test('Child nameserver register, modify, delete', function () use ($hb) {
            $hb->options['NameServer'] = 'ns1';
            $hb->options['NameServerIP'] = '192.0.2.55';
            TestRunner::assertTrue($hb->registerNameServer());

            $hb->options['NameServerNewIP'] = '192.0.2.56';
            TestRunner::assertTrue($hb->modifyNameServer());

            TestRunner::assertTrue($hb->deleteNameServer());
        });

        TestRunner::test('getRegistrarLock and updateRegistrarLock', function () use ($hb) {
            $hb->options['reglock'] = 1;
            TestRunner::assertTrue($hb->updateRegistrarLock());
            TestRunner::assertTrue($hb->getRegistrarLock());

            $hb->options['reglock'] = 0;
            TestRunner::assertTrue($hb->updateRegistrarLock());
            TestRunner::assertFalse($hb->getRegistrarLock());
        });

        TestRunner::test('updateIDProtection auto-purchases addon', function () use ($hb) {
            $hb->options['idprotection'] = 1;
            TestRunner::assertTrue($hb->updateIDProtection());

            $hb->options['idprotection'] = 0;
            TestRunner::assertTrue($hb->updateIDProtection());
        });

        TestRunner::test('getDNSmanagement and updateDNSManagement auto-purchases addon', function () use ($hb) {
            $hb->options['sld'] = 'hb-dns-autoaddon';
            $hb->options['dnsmanagement'] = 1;
            $hb->options['records'] = [
                ['name' => '@', 'type' => 'A', 'content' => '1.2.3.4', 'ttl' => 3600],
                ['name' => 'mail', 'type' => 'MX', 'content' => 'mail.example.com', 'priority' => 10, 'ttl' => 3600]
            ];
            // updateDNSManagement should catch ADDON_NOT_ACTIVE, ensureAddon(DnsManagement), and succeed!
            TestRunner::assertTrue($hb->updateDNSManagement());

            $records = $hb->getDNSmanagement();
            TestRunner::assertNotEmpty($records);
        });

        TestRunner::test('synchInfo retrieves and maps domain details', function () use ($hb) {
            $hb->options['sld'] = 'hostbill-domain';
            $sync = $hb->synchInfo();
            TestRunner::assertEquals('Active', $sync['status']);
            TestRunner::assertNotEmpty($sync['expires']);
            TestRunner::assertNotEmpty($sync['nameservers']);
        });
    });
}

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $baseUrl = $argv[1] ?? 'http://127.0.0.1:8999';
    runHostBillTests($baseUrl);
    exit(TestRunner::summary());
}

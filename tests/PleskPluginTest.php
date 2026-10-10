<?php

declare(strict_types=1);

namespace DomainRegistrar\Tests;

require_once __DIR__ . '/TestRunner.php';

require_once __DIR__ . '/PlatformMocks.php';

require_once __DIR__ . '/../plesk/plib/library/Settings.php';
require_once __DIR__ . '/../plesk/plib/library/Registrar.php';
require_once __DIR__ . '/../plesk/plib/library/EventListener.php';

function runPleskTests(string $baseUrl): void
{
    TestRunner::suite('Plesk Extension Integration Tests', function () use ($baseUrl) {
        \pm_Settings::$settings = [
            'auto_register' => '1',
            'api_key' => 'pk_test_abc123',
            'api_secret' => 'sk_test_xyz789',
            'api_url' => $baseUrl,
            'years' => '1',
            'only_tlds' => '.com, .net',
            'nameservers' => 'ns1.plesk.com, ns2.plesk.com',
            'whois_privacy' => '1',
            'dns_management' => '1',
            'auto_renew' => '1',
            'registrant_first_name' => 'Plesk',
            'registrant_last_name' => 'Admin',
            'registrant_organization' => 'Plesk Corp',
            'registrant_address1' => '123 Server Rd',
            'registrant_city' => 'Seattle',
            'registrant_state' => 'WA',
            'registrant_postal_code' => '98101',
            'registrant_country_code' => 'US',
            'registrant_email' => 'admin@plesk.com',
            'registrant_phone' => '+1.5551234567'
        ];

        TestRunner::test('Modules_Paknic_Settings parses settings correctly', function () {
            TestRunner::assertTrue(\Modules_Paknic_Settings::flag('auto_register'));
            TestRunner::assertEquals('pk_test_abc123', \Modules_Paknic_Settings::get('api_key'));
            TestRunner::assertEquals('sk_test_xyz789', \Modules_Paknic_Settings::apiSecret());
            TestRunner::assertEquals(['.com', '.net'], \Modules_Paknic_Settings::onlyTlds());
            TestRunner::assertEquals(['ns1.plesk.com', 'ns2.plesk.com'], \Modules_Paknic_Settings::nameservers());
            TestRunner::assertEquals('Plesk', \Modules_Paknic_Settings::registrant()['first_name']);
        });

        TestRunner::test('Modules_Paknic_Registrar stops when auto_register is off', function () {
            \pm_Settings::$settings['auto_register'] = '0';
            $msg = \Modules_Paknic_Registrar::registerIfAvailable('test.com');
            TestRunner::assertStringContainsString('automatic registration is off', $msg);
            \pm_Settings::$settings['auto_register'] = '1';
        });

        TestRunner::test('Modules_Paknic_Registrar stops when TLD is not allowed', function () {
            $msg = \Modules_Paknic_Registrar::registerIfAvailable('test.org');
            TestRunner::assertStringContainsString('TLD not in the allowed list', $msg);
        });

        TestRunner::test('Modules_Paknic_Registrar skips already registered domain', function () {
            $msg = \Modules_Paknic_Registrar::registerIfAvailable('registered.com');
            TestRunner::assertStringContainsString('already registered, nothing to do', $msg);
        });

        TestRunner::test('Modules_Paknic_Registrar registers available domain', function () {
            $msg = \Modules_Paknic_Registrar::registerIfAvailable('avail-plesk.com', 'owner@example.com');
            TestRunner::assertStringContainsString('registered (status Active', $msg);
        });

        TestRunner::test('Modules_Paknic_EventListener handles domain_create event', function () {
            $listener = new \Modules_Paknic_EventListener();
            TestRunner::assertEquals(['domain_create'], $listener->filterActions());

            \pm_Log::$messages = [];
            $listener->handleEvent('domain', 101, 'domain_create', [], ['Domain Name' => 'avail-plesk.com']);
            TestRunner::assertNotEmpty(\pm_Log::$messages);
            TestRunner::assertStringContainsString('avail-plesk.com', \pm_Log::$messages[0]);
        });
    });
}

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $baseUrl = $argv[1] ?? 'http://127.0.0.1:8999';
    runPleskTests($baseUrl);
    exit(TestRunner::summary());
}

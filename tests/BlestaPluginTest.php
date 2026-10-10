<?php

declare(strict_types=1);

namespace DomainRegistrar\Tests;

require_once __DIR__ . '/TestRunner.php';

require_once __DIR__ . '/PlatformMocks.php';

require_once __DIR__ . '/../blesta/components/modules/paknic/paknic.php';

function runBlestaTests(string $baseUrl): void
{
    TestRunner::suite('Blesta paknic.php Integration Tests', function () use ($baseUrl) {
        $blesta = new \Paknic();
        $meta = new \stdClass();
        $meta->api_key = 'pk_test_abc123';
        $meta->api_secret = 'sk_test_xyz789';
        $meta->api_url = $baseUrl;
        $blesta->meta = $meta;

        TestRunner::test('checkAvailability returns true for available and false for taken', function () use ($blesta) {
            TestRunner::assertTrue($blesta->checkAvailability('avail-blesta.com'));
            TestRunner::assertFalse($blesta->checkAvailability('registered.com'));
        });

        TestRunner::test('registerDomain creates domain and returns result', function () use ($blesta) {
            $vars = [
                'years' => 1,
                'nameservers' => ['ns1.paknic.com', 'ns2.paknic.com'],
                'first_name' => 'Jane',
                'last_name' => 'Blesta',
                'company' => 'Blesta Inc',
                'address1' => '456 Main St',
                'city' => 'Austin',
                'state' => 'TX',
                'zip' => '78701',
                'country' => 'US',
                'email' => 'jane@blesta.com',
                'phone' => '5551234567',
                'country_calling_code' => '1',
                'privacy' => true
            ];
            $res = $blesta->registerDomain('blesta-reg.com', null, $vars);
            TestRunner::assertNotEmpty($res);
            TestRunner::assertEquals('Active', $res['status']);
        });

        TestRunner::test('renewDomain extends domain term', function () use ($blesta) {
            $res = $blesta->renewDomain('blesta-reg.com', null, ['years' => 1]);
            TestRunner::assertNotEmpty($res);
            TestRunner::assertEquals(1, $res['years_added']);
        });

        TestRunner::test('transferDomain initiates transfer', function () use ($blesta) {
            $res = $blesta->transferDomain('blesta-tr.com', null, ['transfer_key' => 'EPP123!']);
            TestRunner::assertNotEmpty($res);
            TestRunner::assertEquals('Pending', $res['status']);
        });

        TestRunner::test('getDomainInfo returns details', function () use ($blesta) {
            $info = $blesta->getDomainInfo('example.com');
            TestRunner::assertNotEmpty($info);
            TestRunner::assertEquals('example.com', $info['domain']);
        });

        TestRunner::test('getNameServers and setNameServers', function () use ($blesta) {
            $ns = $blesta->getNameServers('example.com');
            TestRunner::assertNotEmpty($ns);

            $saved = $blesta->setNameServers('example.com', null, ['ns1.blesta.com', 'ns2.blesta.com']);
            TestRunner::assertNotEmpty($saved);
        });

        TestRunner::test('getLockStatus and setLockStatus', function () use ($blesta) {
            $lock = $blesta->setLockStatus('blesta-lock.com', 'true');
            TestRunner::assertNotEmpty($lock);

            $status = $blesta->getLockStatus('blesta-lock.com');
            TestRunner::assertEquals('true', $status);

            $unlock = $blesta->setLockStatus('blesta-lock.com', 'false');
            TestRunner::assertNotEmpty($unlock);

            $statusUnlocked = $blesta->getLockStatus('blesta-lock.com');
            TestRunner::assertEquals('false', $statusUnlocked);
        });

        TestRunner::test('getAuthCode returns epp code', function () use ($blesta) {
            $auth = $blesta->getAuthCode('example.com');
            TestRunner::assertNotEmpty($auth);
        });
    });
}

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $baseUrl = $argv[1] ?? 'http://127.0.0.1:8999';
    runBlestaTests($baseUrl);
    exit(TestRunner::summary());
}

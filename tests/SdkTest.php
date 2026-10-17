<?php

declare(strict_types=1);

namespace DomainRegistrar\Tests;

require_once __DIR__ . '/../sdk-php/autoload.php';
require_once __DIR__ . '/TestRunner.php';

use DomainRegistrar\SDK\Client;
use DomainRegistrar\SDK\Configuration;
use DomainRegistrar\SDK\Exceptions\AuthenticationException;
use DomainRegistrar\SDK\Exceptions\InsufficientFundsException;
use DomainRegistrar\SDK\Exceptions\ValidationException;
use DomainRegistrar\SDK\Exceptions\DomainRegistrarException;
use DomainRegistrar\SDK\Services\AddonService;
use DomainRegistrar\SDK\Services\DomainService;
use DomainRegistrar\SDK\Services\WebhookService;

function runSdkTests(string $baseUrl): void
{
    TestRunner::suite('SDK Configuration & Client Unit Tests', function () use ($baseUrl) {
        TestRunner::test('Configuration normalizes base URL', function () {
            TestRunner::assertEquals('https://api.paknic.com', Configuration::normalizeBaseUrl('https://api.paknic.com/api/v1'));
            TestRunner::assertEquals('https://api.paknic.com', Configuration::normalizeBaseUrl('https://api.paknic.com/API/V1/'));
            TestRunner::assertEquals('http://localhost:5000', Configuration::normalizeBaseUrl('http://localhost:5000/'));
            TestRunner::assertEquals('https://api.paknic.com', Configuration::normalizeBaseUrl(''));
        });

        TestRunner::test('Configuration detects sandbox keys', function () {
            $sandboxConfig = new Configuration('pk_test_12345', 'sk_test_67890');
            TestRunner::assertTrue($sandboxConfig->isSandbox());

            $liveConfig = new Configuration('pk_live_12345', 'sk_live_67890');
            TestRunner::assertFalse($liveConfig->isSandbox());
        });

        TestRunner::test('WebhookService signature verification', function () {
            $secret = 'whsec_test_secret_key_12345';
            $payload = json_encode(['event' => 'domain.registered', 'domain' => 'test.com']);
            $validSig = hash_hmac('sha256', $payload, $secret);

            TestRunner::assertTrue(WebhookService::verifySignature($payload, $validSig, $secret));
            TestRunner::assertFalse(WebhookService::verifySignature($payload, 'invalid_sig', $secret));
            TestRunner::assertFalse(WebhookService::verifySignature($payload . 'x', $validSig, $secret));
        });

        TestRunner::test('AddonService isActive helper', function () {
            $addons = [
                ['service_code' => 'WhoisPrivacy', 'is_active' => true, 'is_disabled_by_admin' => false],
                ['service_code' => 'DnsManagement', 'is_active' => false, 'is_disabled_by_admin' => false],
                ['service_code' => 'EmailForwarding', 'is_active' => true, 'is_disabled_by_admin' => true],
            ];

            TestRunner::assertTrue(AddonService::isActive($addons, 'WhoisPrivacy'));
            TestRunner::assertFalse(AddonService::isActive($addons, 'DnsManagement'));
            TestRunner::assertFalse(AddonService::isActive($addons, 'EmailForwarding')); // disabled by admin
            TestRunner::assertFalse(AddonService::isActive($addons, 'NonExistent'));
        });

        TestRunner::test('DomainService isLocked helper', function () {
            TestRunner::assertTrue(DomainService::isLocked(['is_transfer_locked' => true]));
            TestRunner::assertFalse(DomainService::isLocked(['is_transfer_locked' => false]));
            TestRunner::assertFalse(DomainService::isLocked([]));
        });
    });

    TestRunner::suite('SDK Live/Mock End-to-End Tests', function () use ($baseUrl) {
        $config = new Configuration('pk_test_abc123', 'sk_test_xyz789', $baseUrl);
        $client = new Client($config);

        TestRunner::test('AccountService::getBalance', function () use ($client) {
            $res = $client->account()->getBalance();
            TestRunner::assertEquals('USD', $res['currency']);
            TestRunner::assertEquals(1250.75, (float)$res['balance']);
            TestRunner::assertEquals(100.0, (float)$res['minimum_threshold']);
        });

        TestRunner::test('AccountService::updateThreshold', function () use ($client) {
            $res = $client->account()->updateThreshold(150.00, true, 250.00);
            TestRunner::assertEquals(150.00, (float)$res['minimum_threshold']);
            TestRunner::assertEquals(250.00, (float)$res['auto_top_up_amount']);
            TestRunner::assertTrue($res['auto_top_up_enabled']);
        });

        TestRunner::test('AccountService::getTransactions', function () use ($client) {
            $res = $client->account()->getTransactions(1, 10);
            TestRunner::assertNotEmpty($res);
            TestRunner::assertEquals('Debit', $res[0]['type']);
        });

        TestRunner::test('AccountService::getPricing', function () use ($client) {
            $pricing = $client->account()->getPricing();
            TestRunner::assertNotEmpty($pricing);
            TestRunner::assertEquals('.com', $pricing[0]['tld']);
            TestRunner::assertEquals('USD', $pricing[0]['currency']);
            TestRunner::assertEquals(10.99, (float)$pricing[0]['register_price']);
        });

        TestRunner::test('TldService::lifecycle', function () use ($client) {
            $tlds = $client->tlds()->lifecycle();
            TestRunner::assertEquals(2, count($tlds));
            TestRunner::assertEquals('sunsetting', $tlds[0]['status']);
            TestRunner::assertFalse(!empty($tlds[0]['registration_open']));
            TestRunner::assertTrue(!empty($tlds[0]['renewals_open']));
        });

        TestRunner::test('DomainService::check available & taken', function () use ($client) {
            $avail = $client->domains()->check('avail-domain.com');
            TestRunner::assertTrue(!empty($avail['is_available']));

            $taken = $client->domains()->check('registered-domain.com');
            TestRunner::assertFalse(!empty($taken['is_available']));
        });

        TestRunner::test('DomainService::bulkCheck', function () use ($client) {
            $res = $client->domains()->bulkCheck(['avail-1.com', 'taken-2.com']);
            TestRunner::assertCount(2, $res['results']);
        });

        TestRunner::test('DomainService::suggest', function () use ($client) {
            $res = $client->domains()->suggest('mybrand', 5);
            TestRunner::assertNotEmpty($res);
        });

        TestRunner::test('DomainService::register', function () use ($client) {
            $res = $client->domains()->register(
                'newregistered.com',
                1,
                ['ns1.paknic.com', 'ns2.paknic.com'],
                [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'organization' => 'Paknic',
                    'address1' => '123 Main St',
                    'city' => 'New York',
                    'state' => 'NY',
                    'postal_code' => '10001',
                    'country_code' => 'US',
                    'email' => 'john@example.com',
                    'phone' => '+1.5551234567'
                ],
                true, // privacy
                true, // auto renew
                true  // dns management
            );
            TestRunner::assertEquals('newregistered.com', $res['domain']);
            TestRunner::assertEquals('Active', $res['status']);
        });

        TestRunner::test('DomainService::renew', function () use ($client) {
            $res = $client->domains()->renew('newregistered.com', 2);
            TestRunner::assertEquals('newregistered.com', $res['domain']);
            TestRunner::assertEquals(2, $res['years_added']);
        });

        TestRunner::test('DomainService::transfer and getTransferStatus', function () use ($client) {
            $tr = $client->domains()->transfer('transferdomain.com', 'AuthCode123!');
            TestRunner::assertEquals('transferdomain.com', $tr['domain']);

            $status = $client->domains()->getTransferStatus('completed-transfer.com');
            TestRunner::assertEquals('Completed', $status['status']);

            $rejected = $client->domains()->getTransferStatus('rejected-transfer.com');
            TestRunner::assertEquals('Rejected', $rejected['status']);
        });

        TestRunner::test('DomainService::getDetails and listPortfolio', function () use ($client) {
            $details = $client->domains()->getDetails('example.com');
            TestRunner::assertEquals('example.com', $details['domain']);
            TestRunner::assertEquals('Active', $details['status']);

            $portfolio = $client->domains()->listPortfolio();
            TestRunner::assertNotEmpty($portfolio);
        });

        TestRunner::test('DomainService lock, privacy, auto-renew, auth-code', function () use ($client) {
            // Lock
            $lockRes = $client->domains()->setLock('domain-lock.com', true);
            TestRunner::assertTrue($lockRes['is_transfer_locked']);
            $details = $client->domains()->getDetails('domain-lock.com');
            TestRunner::assertTrue(DomainService::isLocked($details));

            $unlockRes = $client->domains()->setLock('domain-lock.com', false);
            TestRunner::assertFalse($unlockRes['is_transfer_locked']);

            // Auth code
            $auth = $client->domains()->getAuthCode('domain-lock.com');
            TestRunner::assertNotEmpty($auth['auth_code']);

            // Auto-renew
            $ar = $client->domains()->setAutoRenew('domain-lock.com', true);
            TestRunner::assertTrue($ar['auto_renew']);
        });

        TestRunner::test('AddonService listAddons, purchase, setAutoRenew', function () use ($client) {
            $domain = 'domain-addons.com';
            // Initially no addons active
            $addonsBefore = $client->addons()->listAddons($domain);
            TestRunner::assertFalse(AddonService::isActive($addonsBefore, AddonService::WHOIS_PRIVACY));

            // Purchase WhoisPrivacy
            $bought = $client->addons()->purchase($domain, AddonService::WHOIS_PRIVACY, 1);
            TestRunner::assertEquals(AddonService::WHOIS_PRIVACY, $bought['service_code']);

            // Now active
            $addonsAfter = $client->addons()->listAddons($domain);
            TestRunner::assertTrue(AddonService::isActive($addonsAfter, AddonService::WHOIS_PRIVACY));

            // Privacy toggle now succeeds
            $privRes = $client->domains()->setPrivacy($domain, true);
            TestRunner::assertTrue($privRes['is_privacy_enabled']);

            // Set auto-renew on add-on
            $ar = $client->addons()->setAutoRenew($domain, AddonService::WHOIS_PRIVACY, false);
            TestRunner::assertFalse($ar['auto_renew']);
        });

        TestRunner::test('Privacy toggle fails with 403 ADDON_NOT_ACTIVE when addon not purchased', function () use ($client) {
            $domain = 'no-privacy-addon.com';
            TestRunner::assertThrows(DomainRegistrarException::class, function () use ($client, $domain) {
                $client->domains()->setPrivacy($domain, true);
            }, 'ADDON_NOT_ACTIVE');
        });

        TestRunner::test('ContactService getContacts and updateContacts', function () use ($client) {
            $domain = 'contacts-test.com';
            $contacts = $client->contacts()->getContacts($domain);
            TestRunner::assertEquals('John', $contacts['registrant']['first_name']);

            $updated = $client->contacts()->updateContacts(
                $domain,
                [
                    'first_name' => 'Alice',
                    'last_name' => 'Smith',
                    'address1' => '456 Oak St',
                    'city' => 'Chicago',
                    'state' => 'IL',
                    'postal_code' => '60601',
                    'country_code' => 'US',
                    'email' => 'alice@example.com',
                    'phone' => '+1.5559876543'
                ]
            );
            TestRunner::assertEquals('Alice', $updated['registrant']['first_name']);
        });

        TestRunner::test('NameserverService nameservers and child hosts', function () use ($client) {
            $domain = 'ns-test.com';
            $ns = $client->nameservers()->getNameservers($domain);
            TestRunner::assertNotEmpty($ns['nameservers']);

            $saved = $client->nameservers()->saveNameservers($domain, ['ns1.new.com', 'ns2.new.com']);
            TestRunner::assertCount(2, $saved['nameservers']);

            // Child host
            $host = 'ns1.' . $domain;
            $created = $client->nameservers()->createChildHost($domain, $host, ['192.0.2.1']);
            TestRunner::assertEquals($host, $created['host']);

            $del = $client->nameservers()->deleteChildHost($domain, $host);
            TestRunner::assertEquals($host, $del['host']);
        });

        TestRunner::test('DnsService records and DNSSEC', function () use ($client) {
            $domain = 'dns-test.com';
            // First purchase DnsManagement addon
            $client->addons()->purchase($domain, AddonService::DNS_MANAGEMENT, 1);

            $records = [
                ['name' => '@', 'type' => 'A', 'content' => '192.0.2.10', 'ttl' => 3600],
                ['name' => 'www', 'type' => 'CNAME', 'content' => $domain, 'ttl' => 3600]
            ];
            $saved = $client->dns()->saveZoneRecords($domain, $records);
            TestRunner::assertCount(2, $saved['records']);

            $fetched = $client->dns()->getZoneRecords($domain);
            TestRunner::assertCount(2, $fetched['records']);

            // DNSSEC
            $added = $client->dns()->addDnsSecRecord($domain, 12345, 13, 2, '49FD46E6C4B45C55D4AC');
            TestRunner::assertEquals(12345, $added['key_tag']);

            $dnssecList = $client->dns()->getDnsSecRecords($domain);
            TestRunner::assertNotEmpty($dnssecList);

            $del = $client->dns()->deleteDnsSecRecord($domain, 12345);
            TestRunner::assertEquals(12345, $del['key_tag']);
        });

        TestRunner::test('DnsService saveZoneRecords fails with 403 ADDON_NOT_ACTIVE without addon', function () use ($client) {
            $domain = 'no-dns-addon.com';
            TestRunner::assertThrows(DomainRegistrarException::class, function () use ($client, $domain) {
                $client->dns()->saveZoneRecords($domain, [['name' => '@', 'type' => 'A', 'content' => '1.1.1.1', 'ttl' => 3600]]);
            }, 'ADDON_NOT_ACTIVE');
        });

        TestRunner::test('WebhookService subscriptions', function () use ($client) {
            $subs = $client->webhooks()->getSubscriptions();
            TestRunner::assertNotEmpty($subs);

            $created = $client->webhooks()->createSubscription('https://myhook.com/wh', 'Test Hook', ['domain.*']);
            TestRunner::assertEquals('https://myhook.com/wh', $created['target_url']);

            $del = $client->webhooks()->deleteSubscription('00000000-0000-0000-0000-000000000002');
            TestRunner::assertNotEmpty($del['id']);
        });

        TestRunner::test('AuthenticationException thrown on 401', function () use ($baseUrl) {
            $badClient = new Client(new Configuration('invalid_key', 'invalid_secret', $baseUrl));
            TestRunner::assertThrows(AuthenticationException::class, function () use ($badClient) {
                $badClient->account()->getBalance();
            }, 'AUTHENTICATION_FAILED');
        });

        TestRunner::test('InsufficientFundsException thrown on 402', function () use ($client) {
            TestRunner::assertThrows(InsufficientFundsException::class, function () use ($client) {
                $client->domains()->register('insufficient-funds.com', 1, [], [
                    'first_name' => 'A', 'last_name' => 'B', 'address1' => 'C', 'city' => 'D', 'state' => 'E', 'postal_code' => 'F', 'country_code' => 'US', 'email' => 'a@b.com', 'phone' => '+1.1'
                ]);
            }, 'INSUFFICIENT_FUNDS');
        });

        TestRunner::test('ValidationException thrown on 400 with details', function () use ($client) {
            $ex = TestRunner::assertThrows(ValidationException::class, function () use ($client) {
                $client->domains()->register('', 1, [], []);
            }, 'VALIDATION_FAILED');
            TestRunner::assertNotEmpty($ex->getDetails());
        });
    });
}

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $baseUrl = $argv[1] ?? 'http://127.0.0.1:8999';
    runSdkTests($baseUrl);
    exit(TestRunner::summary());
}

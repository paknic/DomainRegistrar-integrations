<?php

declare(strict_types=1);

namespace DomainRegistrar\Tests;

require_once __DIR__ . '/TestRunner.php';

require_once __DIR__ . '/PlatformMocks.php';

class ClientExecSettings {
    private array $data = [];
    public function __construct(array $data) {
        $this->data = $data;
    }
    public function get(string $key) {
        return $this->data[$key] ?? null;
    }
}

require_once __DIR__ . '/../clientexec/plugins/registrars/paknic/PluginPaknic.php';

function runClientExecTests(string $baseUrl): void
{
    TestRunner::suite('ClientExec PluginPaknic Integration Tests', function () use ($baseUrl) {
        $ce = new \PluginPaknic();
        $ce->settings = new ClientExecSettings([
            'plugin_paknic_API Key' => 'pk_test_abc123',
            'plugin_paknic_API Secret' => 'sk_test_xyz789',
            'plugin_paknic_API URL' => $baseUrl,
        ]);

        TestRunner::test('getVariables returns configuration fields', function () use ($ce) {
            $vars = $ce->getVariables();
            TestRunner::assertNotEmpty($vars['API Key']);
            TestRunner::assertNotEmpty($vars['API Secret']);
            TestRunner::assertNotEmpty($vars['API URL']);
        });

        TestRunner::test('checkDomain returns 0 for available, 1 for taken', function () use ($ce) {
            $avail = $ce->checkDomain(['sld' => 'avail-ce', 'tld' => 'com']);
            TestRunner::assertEquals(0, $avail);

            $taken = $ce->checkDomain(['sld' => 'registered', 'tld' => 'com']);
            TestRunner::assertEquals(1, $taken);
        });

        TestRunner::test('registerDomain registers domain successfully', function () use ($ce) {
            $params = [
                'sld' => 'ce-registered',
                'tld' => 'com',
                'NumYears' => 1,
                'NS1' => 'ns1.paknic.com',
                'NS2' => 'ns2.paknic.com',
                'RegistrantFirstName' => 'John',
                'RegistrantLastName' => 'Smith',
                'RegistrantOrganizationName' => 'CE Corp',
                'RegistrantAddress1' => '100 Broadway',
                'RegistrantCity' => 'New York',
                'RegistrantStateProvince' => 'NY',
                'RegistrantPostalCode' => '10005',
                'RegistrantCountry' => 'US',
                'RegistrantEmailAddress' => 'john@cecorp.com',
                'RegistrantPhone' => '+1.5551234567',
                'idprotection' => 1,
            ];
            $res = $ce->registerDomain($params);
            TestRunner::assertTrue($res);
        });

        TestRunner::test('renewDomain renews domain successfully', function () use ($ce) {
            $res = $ce->renewDomain(['sld' => 'ce-registered', 'tld' => 'com', 'NumYears' => 1]);
            TestRunner::assertTrue($res);
        });

        TestRunner::test('getEPPCode retrieves auth code', function () use ($ce) {
            $code = $ce->getEPPCode(['sld' => 'ce-registered', 'tld' => 'com']);
            TestRunner::assertNotEmpty($code);
        });
    });
}

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $baseUrl = $argv[1] ?? 'http://127.0.0.1:8999';
    runClientExecTests($baseUrl);
    exit(TestRunner::summary());
}

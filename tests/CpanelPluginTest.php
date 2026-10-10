<?php

declare(strict_types=1);

namespace DomainRegistrar\Tests;

require_once __DIR__ . '/TestRunner.php';

function runCpanelTests(string $baseUrl): void
{
    TestRunner::suite('cPanel/WHM Hook Script Integration Tests', function () use ($baseUrl) {
        $scriptPath = __DIR__ . '/../cpanel-whm/whm/scripts/post_create_account_register_domain.php';
        $tempConfig = sys_get_temp_dir() . '/cpanel_whm_test_config.json';

        $runHook = static function (array $contextData, ?string $configFilePath = null): array {
            $script = __DIR__ . '/../cpanel-whm/whm/scripts/post_create_account_register_domain.php';
            $env = $_ENV;
            if ($configFilePath !== null) {
                $env['PAKNIC_WHM_CONFIG'] = $configFilePath;
            } else {
                unset($env['PAKNIC_WHM_CONFIG']);
            }

            $phpBin = PHP_BINARY;
            $proc = proc_open(
                escapeshellarg($phpBin) . ' ' . escapeshellarg($script),
                [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w']
                ],
                $pipes,
                null,
                $env
            );

            fwrite($pipes[0], json_encode(['data' => $contextData]));
            fclose($pipes[0]);

            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            $code = proc_close($proc);
            return ['code' => $code, 'stdout' => trim($stdout), 'stderr' => trim($stderr)];
        };

        TestRunner::test('cPanel hook exits cleanly when domain is missing or invalid', function () use ($runHook) {
            $res = $runHook(['domain' => '']);
            TestRunner::assertEquals(0, $res['code']);
            TestRunner::assertStringContainsString('No domain to register', $res['stdout']);

            $resInvalid = $runHook(['domain' => 'localhost']);
            TestRunner::assertEquals(0, $resInvalid['code']);
            TestRunner::assertStringContainsString('No domain to register', $resInvalid['stdout']);
        });

        TestRunner::test('cPanel hook exits when config is missing', function () use ($runHook) {
            $res = $runHook(['domain' => 'mybrand.com'], '/non_existent_config.json');
            TestRunner::assertEquals(0, $res['code']);
            TestRunner::assertStringContainsString('Paknic not configured', $res['stdout']);
        });

        TestRunner::test('cPanel hook exits when auto registration is disabled', function () use ($runHook, $tempConfig) {
            file_put_contents($tempConfig, json_encode(['auto_register_on_account_create' => false]));
            $res = $runHook(['domain' => 'mybrand.com'], $tempConfig);
            TestRunner::assertEquals(0, $res['code']);
            TestRunner::assertStringContainsString('Automatic registration is off', $res['stdout']);
        });

        TestRunner::test('cPanel hook skips domain when TLD is not in only_tlds list', function () use ($runHook, $tempConfig, $baseUrl) {
            file_put_contents($tempConfig, json_encode([
                'auto_register_on_account_create' => true,
                'api_key' => 'pk_test_abc123',
                'api_secret' => 'sk_test_xyz789',
                'api_url' => $baseUrl,
                'only_tlds' => ['.net', '.org'],
                'registrant' => [
                    'first_name' => 'A', 'last_name' => 'B', 'address1' => 'C', 'city' => 'D',
                    'state' => 'E', 'postal_code' => 'F', 'country_code' => 'US', 'phone' => '+1.5551234'
                ]
            ]));
            $res = $runHook(['domain' => 'mybrand.com'], $tempConfig);
            TestRunner::assertEquals(0, $res['code']);
            TestRunner::assertStringContainsString('not in only_tlds', $res['stdout']);
        });

        TestRunner::test('cPanel hook skips domain if already registered', function () use ($runHook, $tempConfig, $baseUrl) {
            file_put_contents($tempConfig, json_encode([
                'auto_register_on_account_create' => true,
                'api_key' => 'pk_test_abc123',
                'api_secret' => 'sk_test_xyz789',
                'api_url' => $baseUrl,
                'only_tlds' => ['.com'],
                'registrant' => [
                    'first_name' => 'John', 'last_name' => 'Host', 'address1' => '123 Main', 'city' => 'Austin',
                    'state' => 'TX', 'postal_code' => '78701', 'country_code' => 'US', 'phone' => '+1.5551234'
                ]
            ]));
            $res = $runHook(['domain' => 'registered.com'], $tempConfig);
            TestRunner::assertEquals(0, $res['code']);
            TestRunner::assertStringContainsString('already registered', $res['stdout']);
        });

        TestRunner::test('cPanel hook registers available domain with configured registrant & add-ons', function () use ($runHook, $tempConfig, $baseUrl) {
            file_put_contents($tempConfig, json_encode([
                'auto_register_on_account_create' => true,
                'api_key' => 'pk_test_abc123',
                'api_secret' => 'sk_test_xyz789',
                'api_url' => $baseUrl,
                'years' => 1,
                'default_nameservers' => ['ns1.cpanel.com', 'ns2.cpanel.com'],
                'whois_privacy' => true,
                'dns_management' => true,
                'registrant' => [
                    'first_name' => 'John', 'last_name' => 'Host', 'address1' => '123 Main', 'city' => 'Austin',
                    'state' => 'TX', 'postal_code' => '78701', 'country_code' => 'US', 'phone' => '+1.5551234'
                ]
            ]));
            $res = $runHook([
                'domain' => 'avail-cpanel.com',
                'user' => 'cpaneluser',
                'contactemail' => 'client@cpaneluser.com'
            ], $tempConfig);

            TestRunner::assertEquals(0, $res['code']);
            TestRunner::assertStringContainsString('Registered avail-cpanel.com', $res['stdout']);
        });

        if (file_exists($tempConfig)) {
            unlink($tempConfig);
        }
    });
}

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $baseUrl = $argv[1] ?? 'http://127.0.0.1:8999';
    runCpanelTests($baseUrl);
    exit(TestRunner::summary());
}

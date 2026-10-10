<?php

declare(strict_types=1);

namespace DomainRegistrar\Tests;

require_once __DIR__ . '/TestRunner.php';

function runDirectAdminTests(string $baseUrl): void
{
    TestRunner::suite('DirectAdmin register.php Integration Tests', function () use ($baseUrl) {
        $tempConfig = sys_get_temp_dir() . '/directadmin_test_config.json';

        $runRegisterScript = static function (string $domain, string $username = '', ?string $configFilePath = null): array {
            $script = __DIR__ . '/../directadmin/scripts/register.php';
            $env = $_ENV;
            if ($configFilePath !== null) {
                $env['PAKNIC_DIRECTADMIN_CONFIG'] = $configFilePath;
            } else {
                unset($env['PAKNIC_DIRECTADMIN_CONFIG']);
            }

            $phpBin = PHP_BINARY;
            $cmd = escapeshellarg($phpBin) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($domain) . ' ' . escapeshellarg($username);
            $proc = proc_open(
                $cmd,
                [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w']
                ],
                $pipes,
                null,
                $env
            );

            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            $code = proc_close($proc);
            return ['code' => $code, 'stdout' => trim($stdout), 'stderr' => trim($stderr)];
        };

        TestRunner::test('DirectAdmin script ignores invalid domain', function () use ($runRegisterScript) {
            $res = $runRegisterScript('invalid_domain');
            TestRunner::assertEquals(0, $res['code']);
            TestRunner::assertEquals('', $res['stdout']);
        });

        TestRunner::test('DirectAdmin script exits when auto_register is 0', function () use ($runRegisterScript, $tempConfig) {
            file_put_contents($tempConfig, json_encode(['auto_register' => '0']));
            $res = $runRegisterScript('mybrand.com', 'admin', $tempConfig);
            TestRunner::assertEquals(0, $res['code']);
            TestRunner::assertEquals('', $res['stdout']);
        });

        TestRunner::test('DirectAdmin script logs missing credentials', function () use ($runRegisterScript, $tempConfig) {
            file_put_contents($tempConfig, json_encode(['auto_register' => '1', 'api_key' => '']));
            $res = $runRegisterScript('mybrand.com', 'admin', $tempConfig);
            TestRunner::assertEquals(0, $res['code']);
            TestRunner::assertStringContainsString('API key or secret not set', $res['stdout']);
        });

        TestRunner::test('DirectAdmin script checks allowed TLDs', function () use ($runRegisterScript, $tempConfig, $baseUrl) {
            file_put_contents($tempConfig, json_encode([
                'auto_register' => '1',
                'api_key' => 'pk_test_abc123',
                'api_secret' => 'sk_test_xyz789',
                'api_url' => $baseUrl,
                'only_tlds' => '.net, .org'
            ]));
            $res = $runRegisterScript('mybrand.com', 'admin', $tempConfig);
            TestRunner::assertEquals(0, $res['code']);
            TestRunner::assertStringContainsString('TLD not in the allowed list', $res['stdout']);
        });

        TestRunner::test('DirectAdmin script skips already registered domain', function () use ($runRegisterScript, $tempConfig, $baseUrl) {
            file_put_contents($tempConfig, json_encode([
                'auto_register' => '1',
                'api_key' => 'pk_test_abc123',
                'api_secret' => 'sk_test_xyz789',
                'api_url' => $baseUrl,
                'only_tlds' => '.com',
                'registrant_first_name' => 'DA',
                'registrant_last_name' => 'Admin',
                'registrant_address1' => '100 DA Way',
                'registrant_city' => 'Toronto',
                'registrant_state' => 'ON',
                'registrant_postal_code' => 'M5V',
                'registrant_country_code' => 'CA',
                'registrant_phone' => '+1.5551234'
            ]));
            $res = $runRegisterScript('registered.com', 'admin', $tempConfig);
            TestRunner::assertEquals(0, $res['code']);
            TestRunner::assertStringContainsString('already registered, nothing to do', $res['stdout']);
        });

        TestRunner::test('DirectAdmin script registers available domain successfully', function () use ($runRegisterScript, $tempConfig, $baseUrl) {
            file_put_contents($tempConfig, json_encode([
                'auto_register' => '1',
                'api_key' => 'pk_test_abc123',
                'api_secret' => 'sk_test_xyz789',
                'api_url' => $baseUrl,
                'only_tlds' => '.com',
                'nameservers' => 'ns1.da.com, ns2.da.com',
                'whois_privacy' => '1',
                'dns_management' => '1',
                'registrant_first_name' => 'DA',
                'registrant_last_name' => 'Admin',
                'registrant_address1' => '100 DA Way',
                'registrant_city' => 'Toronto',
                'registrant_state' => 'ON',
                'registrant_postal_code' => 'M5V',
                'registrant_country_code' => 'CA',
                'registrant_phone' => '+1.5551234'
            ]));
            $res = $runRegisterScript('avail-da.com', 'dauser', $tempConfig);
            TestRunner::assertEquals(0, $res['code']);
            TestRunner::assertStringContainsString('registered for user dauser', $res['stdout']);
            TestRunner::assertStringContainsString('status Active', $res['stdout']);
        });

        if (file_exists($tempConfig)) {
            unlink($tempConfig);
        }
    });
}

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $baseUrl = $argv[1] ?? 'http://127.0.0.1:8999';
    runDirectAdminTests($baseUrl);
    exit(TestRunner::summary());
}

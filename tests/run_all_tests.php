<?php

declare(strict_types=1);

namespace DomainRegistrar\Tests;

error_reporting(E_ALL & ~E_DEPRECATED);

$port = 8999;
$host = '127.0.0.1';
$baseUrl = "http://{$host}:{$port}";

echo "Starting Mock API Server on {$baseUrl}...\n";

$phpBin = PHP_BINARY;
$serverScript = __DIR__ . '/MockApiServer.php';

$logFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mock_server.log';
$errFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mock_server_err.log';

$cmd = escapeshellarg($phpBin) . " -S {$host}:{$port} " . escapeshellarg($serverScript);
$proc = proc_open(
    $cmd,
    [
        0 => ['pipe', 'r'],
        1 => ['file', $logFile, 'w'],
        2 => ['file', $errFile, 'w']
    ],
    $pipes
);

if (isset($pipes[0])) {
    fclose($pipes[0]);
}

if (!is_resource($proc)) {
    fwrite(STDERR, "Failed to start mock API server.\n");
    exit(1);
}

// Give server 1 full second to bind socket
sleep(1);

$ready = false;
$lastErr = '';
$lastCode = 0;
for ($i = 0; $i < 30; $i++) {
    $ch = curl_init("{$baseUrl}/api/v1/test/reset");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_POST, true);
    $out = curl_exec($ch);
    $lastCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $lastErr = (string)curl_error($ch);
    if ($lastCode === 200) {
        $ready = true;
        break;
    }
    usleep(200000); // 200ms
}

function stopMockServer($proc): void {
    if (!is_resource($proc)) {
        return;
    }
    $status = proc_get_status($proc);
    if (!empty($status['pid'])) {
        if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
            exec("taskkill /F /T /PID {$status['pid']} >NUL 2>&1");
        }
    }
    @proc_terminate($proc);
    @proc_close($proc);
}

if (!$ready) {
    fwrite(STDERR, "Mock API server failed to respond on {$baseUrl}. Last code: {$lastCode}, err: {$lastErr}\n");
    if (file_exists($errFile)) {
        fwrite(STDERR, "Server stderr:\n" . file_get_contents($errFile) . "\n");
    }
    stopMockServer($proc);
    exit(1);
}

while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

echo "Mock API Server is ready.\n";

$testFiles = [
    'SDK (sdk-php)' => 'SdkTest.php',
    'WHMCS Plugin' => 'WhmcsPluginTest.php',
    'Blesta Plugin' => 'BlestaPluginTest.php',
    'ClientExec Plugin' => 'ClientExecPluginTest.php',
    'HostBill Plugin' => 'HostBillPluginTest.php',
    'cPanel/WHM Hook' => 'CpanelPluginTest.php',
    'DirectAdmin Plugin' => 'DirectAdminPluginTest.php',
    'Plesk Extension' => 'PleskPluginTest.php',
];

$allPassed = true;
$results = [];

try {
    foreach ($testFiles as $label => $file) {
        echo "\n" . str_repeat('=', 70) . "\n";
        echo " RUNNING SUITE: {$label} ({$file})\n";
        echo str_repeat('=', 70) . "\n";

        // Reset mock server state between suites
        $resetCh = curl_init("{$baseUrl}/api/v1/test/reset");
        curl_setopt($resetCh, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($resetCh, CURLOPT_POST, true);
        curl_exec($resetCh);
        curl_close($resetCh);

        $cmd = escapeshellarg($phpBin) . ' ' . escapeshellarg(__DIR__ . '/' . $file) . ' ' . escapeshellarg($baseUrl);
        $suiteProc = proc_open($cmd, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ], $suitePipes);

        fclose($suitePipes[0]);
        $stdout = stream_get_contents($suitePipes[1]);
        fclose($suitePipes[1]);
        $stderr = stream_get_contents($suitePipes[2]);
        fclose($suitePipes[2]);

        $exitCode = proc_close($suiteProc);

        echo $stdout;
        if (!empty($stderr)) {
            echo "\n[STDERR]\n" . $stderr . "\n";
        }

        if ($exitCode === 0) {
            $results[$label] = 'PASSED';
        } else {
            $results[$label] = 'FAILED (exit code ' . $exitCode . ')';
            $allPassed = false;
        }
    }
} finally {
    echo "\nStopping Mock API Server...\n";
    stopMockServer($proc);
}

echo "\n" . str_repeat('=', 70) . "\n";
echo " FINAL INTEGRATION SUITE REPORT\n";
echo str_repeat('=', 70) . "\n";
foreach ($results as $label => $status) {
    printf("  %-30s : %s\n", $label, $status);
}
echo str_repeat('=', 70) . "\n";

exit($allPassed ? 0 : 1);

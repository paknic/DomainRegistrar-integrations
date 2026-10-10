<?php

declare(strict_types=1);

namespace DomainRegistrar\Tests;

use Throwable;

class TestRunner
{
    private static int $passed = 0;
    private static int $failed = 0;
    private static array $failures = [];
    private static string $currentSuite = '';

    public static function suite(string $name, callable $callback): void
    {
        self::$currentSuite = $name;
        echo "\n=== Running {$name} ===\n";
        try {
            $callback();
        } catch (Throwable $e) {
            self::fail("Uncaught exception in suite {$name}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }

    public static function test(string $name, callable $callback): void
    {
        try {
            $callback();
            self::$passed++;
            echo "  [PASS] {$name}\n";
        } catch (Throwable $e) {
            self::$failed++;
            $msg = "[FAIL] " . self::$currentSuite . " -> {$name}: " . $e->getMessage();
            self::$failures[] = $msg . "\n" . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString();
            echo "  [FAIL] {$name}: {$e->getMessage()}\n";
        }
    }

    public static function assertEquals($expected, $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            $expectedStr = var_export($expected, true);
            $actualStr = var_export($actual, true);
            throw new \AssertionError($message ?: "Expected {$expectedStr}, got {$actualStr}");
        }
    }

    public static function assertTrue($actual, string $message = ''): void
    {
        if ($actual !== true) {
            throw new \AssertionError($message ?: "Expected true, got " . var_export($actual, true));
        }
    }

    public static function assertFalse($actual, string $message = ''): void
    {
        if ($actual !== false) {
            throw new \AssertionError($message ?: "Expected false, got " . var_export($actual, true));
        }
    }

    public static function assertNotEmpty($actual, string $message = ''): void
    {
        if (empty($actual)) {
            throw new \AssertionError($message ?: "Expected non-empty value, got empty");
        }
    }

    public static function assertCount(int $expectedCount, $actual, string $message = ''): void
    {
        $count = is_countable($actual) ? count($actual) : 0;
        if ($count !== $expectedCount) {
            throw new \AssertionError($message ?: "Expected count {$expectedCount}, got {$count}");
        }
    }

    public static function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
    {
        if (strpos($haystack, $needle) === false) {
            throw new \AssertionError($message ?: "Failed asserting that '{$haystack}' contains '{$needle}'");
        }
    }

    public static function assertThrows(string $expectedClass, callable $callback, ?string $expectedCode = null): Throwable
    {
        try {
            $callback();
        } catch (Throwable $e) {
            if (!($e instanceof $expectedClass)) {
                throw new \AssertionError("Expected exception of type {$expectedClass}, got " . get_class($e) . ": " . $e->getMessage());
            }
            if ($expectedCode !== null && method_exists($e, 'getErrorCode')) {
                if ($e->getErrorCode() !== $expectedCode) {
                    throw new \AssertionError("Expected error code '{$expectedCode}', got '{$e->getErrorCode()}'");
                }
            }
            return $e;
        }
        throw new \AssertionError("Expected exception of type {$expectedClass}, but none was thrown.");
    }

    public static function fail(string $message): void
    {
        self::$failed++;
        self::$failures[] = $message;
        echo "  [FAIL] {$message}\n";
    }

    public static function summary(): int
    {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "TEST SUMMARY: Passed: " . self::$passed . " | Failed: " . self::$failed . "\n";
        if (self::$failed > 0) {
            echo "\nFailures:\n";
            foreach (self::$failures as $idx => $f) {
                echo ($idx + 1) . ". {$f}\n\n";
            }
            echo str_repeat('=', 60) . "\n";
            return 1;
        }
        echo "All tests passed successfully!\n";
        echo str_repeat('=', 60) . "\n";
        return 0;
    }
}

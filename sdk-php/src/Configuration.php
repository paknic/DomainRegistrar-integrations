<?php

declare(strict_types=1);

namespace DomainRegistrar\SDK;

class Configuration
{
    public const DEFAULT_BASE_URL = 'https://api.paknic.com';

    private string $apiKey;
    private string $apiSecret;
    private string $baseUrl;
    private int $timeout;

    /**
     * @param string $baseUrl API host, e.g. https://api.paknic.com. A trailing /api/v1 is removed because
     *                        every endpoint path already starts with it.
     * @param bool $isSandbox Deprecated and ignored. Sandbox mode comes from the key itself: a pk_test_ key
     *                        (with its sk_test_ secret) only reaches the simulator and is never charged.
     */
    public function __construct(
        string $apiKey,
        string $apiSecret,
        string $baseUrl = self::DEFAULT_BASE_URL,
        int $timeout = 30,
        bool $isSandbox = false
    ) {
        $this->apiKey = trim($apiKey);
        $this->apiSecret = trim($apiSecret);
        $this->baseUrl = self::normalizeBaseUrl($baseUrl);
        $this->timeout = $timeout;
    }

    public static function normalizeBaseUrl(string $baseUrl): string
    {
        $url = rtrim(trim($baseUrl), '/');
        if ($url === '') {
            return self::DEFAULT_BASE_URL;
        }
        return (string)preg_replace('#/api/v1$#i', '', $url);
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getApiSecret(): string
    {
        return $this->apiSecret;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * True for a pk_test_ key, which the API runs against the sandbox simulator.
     */
    public function isSandbox(): bool
    {
        return strncmp($this->apiKey, 'pk_test_', 8) === 0;
    }
}

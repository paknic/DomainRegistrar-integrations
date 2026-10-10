<?php

declare(strict_types=1);

namespace DomainRegistrar\SDK;

use DomainRegistrar\SDK\Exceptions\AuthenticationException;
use DomainRegistrar\SDK\Exceptions\DomainRegistrarException;
use DomainRegistrar\SDK\Exceptions\InsufficientFundsException;
use DomainRegistrar\SDK\Exceptions\ValidationException;
use DomainRegistrar\SDK\Services\AccountService;
use DomainRegistrar\SDK\Services\AddonService;
use DomainRegistrar\SDK\Services\ContactService;
use DomainRegistrar\SDK\Services\DnsService;
use DomainRegistrar\SDK\Services\DomainService;
use DomainRegistrar\SDK\Services\NameserverService;
use DomainRegistrar\SDK\Services\WebhookService;

class Client
{
    private Configuration $config;
    private ?DomainService $domainService = null;
    private ?NameserverService $nameserverService = null;
    private ?ContactService $contactService = null;
    private ?DnsService $dnsService = null;
    private ?AccountService $accountService = null;
    private ?WebhookService $webhookService = null;
    private ?AddonService $addonService = null;

    public function __construct(Configuration $config)
    {
        $this->config = $config;
    }

    public function getConfig(): Configuration
    {
        return $this->config;
    }

    public function domains(): DomainService
    {
        if ($this->domainService === null) {
            $this->domainService = new DomainService($this);
        }
        return $this->domainService;
    }

    public function nameservers(): NameserverService
    {
        if ($this->nameserverService === null) {
            $this->nameserverService = new NameserverService($this);
        }
        return $this->nameserverService;
    }

    public function contacts(): ContactService
    {
        if ($this->contactService === null) {
            $this->contactService = new ContactService($this);
        }
        return $this->contactService;
    }

    public function dns(): DnsService
    {
        if ($this->dnsService === null) {
            $this->dnsService = new DnsService($this);
        }
        return $this->dnsService;
    }

    public function account(): AccountService
    {
        if ($this->accountService === null) {
            $this->accountService = new AccountService($this);
        }
        return $this->accountService;
    }

    public function webhooks(): WebhookService
    {
        if ($this->webhookService === null) {
            $this->webhookService = new WebhookService($this);
        }
        return $this->webhookService;
    }

    public function addons(): AddonService
    {
        if ($this->addonService === null) {
            $this->addonService = new AddonService($this);
        }
        return $this->addonService;
    }

    /**
     * Executes an HTTP request to the API gateway.
     *
     * @param string $method GET, POST, PUT, DELETE
     * @param string $endpoint e.g. /api/v1/domains/check
     * @param array $params Query params or JSON payload
     * @return array Decoded response 'data' or envelope
     * @throws DomainRegistrarException
     */
    public function request(string $method, string $endpoint, array $params = []): array
    {
        $url = $this->config->getBaseUrl() . '/' . ltrim($endpoint, '/');
        $method = strtoupper($method);

        $headers = [
            'X-API-Key: ' . $this->config->getApiKey(),
            'X-API-Secret: ' . $this->config->getApiSecret(),
            'Accept: application/json',
            'User-Agent: DomainRegistrar-PHP-SDK/1.0.0 (PHP ' . PHP_VERSION . ')'
        ];

        $ch = curl_init();

        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        } elseif (in_array($method, ['POST', 'PUT', 'DELETE'])) {
            // An empty PHP array encodes as [], which the API cannot bind to a request object.
            $jsonData = empty($params) ? '{}' : json_encode($params);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            $headers[] = 'Content-Type: application/json';
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config->getTimeout());
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $rawResponse = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        if (PHP_VERSION_ID < 80000) {
            curl_close($ch);
        }

        if ($rawResponse === false) {
            throw new DomainRegistrarException('HTTP request failed: ' . $curlError, 0);
        }

        $decoded = json_decode($rawResponse, true);
        if (!is_array($decoded) && $httpCode === 401) {
            // A rejected key or secret gets a bare 401 challenge with no body.
            throw new AuthenticationException('Authentication failed: check the API key, the API secret and the key\'s IP allow-list.', 401, 'AUTHENTICATION_FAILED');
        }
        if (!is_array($decoded) && $httpCode >= 400) {
            throw new DomainRegistrarException('HTTP ' . $httpCode . ' from ' . $url . ' (check the API Base URL).', $httpCode, 'HTTP_' . $httpCode);
        }
        if (!is_array($decoded)) {
            throw new DomainRegistrarException('Invalid JSON response received from API: ' . substr($rawResponse, 0, 250), $httpCode);
        }

        if ($httpCode >= 400 || (isset($decoded['success']) && $decoded['success'] === false)) {
            $message = $decoded['message'] ?? null;
            $code = $decoded['error']['code'] ?? null;
            $details = $decoded['error']['details'] ?? [];

            // ASP.NET model validation answers with ProblemDetails (title + errors) rather than the API envelope.
            if (isset($decoded['errors']) && is_array($decoded['errors'])) {
                foreach ($decoded['errors'] as $field => $fieldErrors) {
                    foreach ((array)$fieldErrors as $fieldError) {
                        $details[] = (is_string($field) && $field !== '' ? $field . ': ' : '') . $fieldError;
                    }
                }
                $code = $code ?? 'VALIDATION_FAILED';
            }
            if ($message === null || $message === '') {
                $message = $decoded['title'] ?? 'Unknown API Error';
            }
            if (!empty($details)) {
                $message .= ' ' . implode(' ', $details);
            }
            $code = $code ?? 'API_ERROR';

            // 403 is not an auth failure here: the API also uses it for ADDON_NOT_ACTIVE and ADDON_DISABLED_BY_ADMIN.
            if ($httpCode === 401 || $code === 'AUTHENTICATION_FAILED') {
                throw new AuthenticationException($message, $httpCode, $code, $details);
            }
            if ($httpCode === 402 || $code === 'INSUFFICIENT_FUNDS') {
                throw new InsufficientFundsException($message, $httpCode, $code, $details);
            }
            if ($httpCode === 400 || $code === 'VALIDATION_FAILED') {
                throw new ValidationException($message, $httpCode, $code, $details);
            }

            throw new DomainRegistrarException($message, $httpCode, $code, $details);
        }

        return $decoded['data'] ?? $decoded;
    }
}

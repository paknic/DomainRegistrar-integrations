<?php

declare(strict_types=1);

namespace DomainRegistrar\SDK\Services;

use DomainRegistrar\SDK\Client;

class WebhookService
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function getSubscriptions(): array
    {
        return $this->client->request('GET', 'api/v1/webhooks/subscriptions');
    }

    public function createSubscription(string $targetUrl, ?string $description = null, array $events = ['*']): array
    {
        return $this->client->request('POST', 'api/v1/webhooks/subscriptions', [
            'target_url' => $targetUrl,
            'description' => $description,
            'subscribed_events' => $events
        ]);
    }

    public function deleteSubscription(string $id): array
    {
        return $this->client->request('DELETE', 'api/v1/webhooks/subscriptions/' . $id);
    }

    /**
     * Verifies an incoming webhook payload using HMAC-SHA256.
     *
     * @param string $rawPayload Raw JSON string received in request body
     * @param string $signatureHeader Value of HTTP header X-Webhook-Signature
     * @param string $secretKey Configured webhook secret
     * @return bool True if valid, false otherwise
     */
    public static function verifySignature(string $rawPayload, string $signatureHeader, string $secretKey): bool
    {
        $expectedSignature = hash_hmac('sha256', $rawPayload, $secretKey);
        return hash_equals($expectedSignature, trim($signatureHeader));
    }
}

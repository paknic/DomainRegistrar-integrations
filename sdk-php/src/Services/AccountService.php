<?php

declare(strict_types=1);

namespace DomainRegistrar\SDK\Services;

use DomainRegistrar\SDK\Client;

class AccountService
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function getBalance(): array
    {
        return $this->client->request('GET', 'api/v1/account/balance');
    }

    public function updateThreshold(float $minimumThreshold, bool $autoTopUpEnabled, float $autoTopUpAmount): array
    {
        return $this->client->request('PUT', 'api/v1/account/threshold', [
            'minimum_threshold' => $minimumThreshold,
            'auto_top_up_enabled' => $autoTopUpEnabled,
            'auto_top_up_amount' => $autoTopUpAmount
        ]);
    }

    public function getTransactions(int $page = 1, int $pageSize = 20): array
    {
        return $this->client->request('GET', 'api/v1/account/transactions', [
            'page' => $page,
            'pageSize' => $pageSize
        ]);
    }

    public function getPricing(): array
    {
        return $this->client->request('GET', 'api/v1/account/pricing');
    }
}

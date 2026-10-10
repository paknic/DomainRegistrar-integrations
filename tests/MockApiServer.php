<?php
/**
 * Mock API Server for DomainRegistrar PHP SDK & Platform Plugins.
 * Run with: php -S 127.0.0.1:8999 MockApiServer.php
 */

declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$query = [];
if (!empty($_SERVER['QUERY_STRING'])) {
    parse_str($_SERVER['QUERY_STRING'], $query);
}

$rawInput = file_get_contents('php://input');
$jsonBody = !empty($rawInput) ? json_decode($rawInput, true) : [];

// State file or memory
$stateFile = sys_get_temp_dir() . '/paknic_mock_state.json';
$state = [
    'locks' => [],
    'privacy' => [],
    'addons' => [],
    'dns' => [],
    'last_request' => null,
];
if (file_exists($stateFile)) {
    $loaded = json_decode((string)file_get_contents($stateFile), true);
    if (is_array($loaded)) {
        $state = array_merge($state, $loaded);
    }
}

function saveState(array $state, string $stateFile): void {
    file_put_contents($stateFile, json_encode($state));
}

if (!function_exists('getallheaders')) {
    function getallheaders(): array {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = (string)$value;
            } elseif ($key === 'CONTENT_TYPE') {
                $headers['Content-Type'] = (string)$value;
            } elseif ($key === 'CONTENT_LENGTH') {
                $headers['Content-Length'] = (string)$value;
            }
        }
        return $headers;
    }
}

// Record last request
$headers = getallheaders();
$state['last_request'] = [
    'method' => $method,
    'uri' => $uri,
    'headers' => $headers,
    'query' => $query,
    'body' => $jsonBody,
    'raw_body' => $rawInput,
];
saveState($state, $stateFile);

header('Content-Type: application/json; charset=utf-8');

// Helper to respond
function jsonResponse(int $statusCode, array $data): void {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

// Reset state
if ($uri === '/api/v1/test/reset') {
    if (file_exists($stateFile)) {
        unlink($stateFile);
    }
    jsonResponse(200, ['success' => true]);
}

// Last request inspect
if ($uri === '/api/v1/test/last-request') {
    jsonResponse(200, ['success' => true, 'data' => $state['last_request']]);
}

// Check Authentication headers
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? ($headers['X-Api-Key'] ?? ($headers['X-API-Key'] ?? ''));
$apiSecret = $_SERVER['HTTP_X_API_SECRET'] ?? ($headers['X-Api-Secret'] ?? ($headers['X-API-Secret'] ?? ''));

if ($apiKey === 'invalid_key' || $apiSecret === 'invalid_secret') {
    jsonResponse(401, [
        'success' => false,
        'message' => "Authentication failed: check the API key, the API secret and the key's IP allow-list.",
        'error' => ['code' => 'AUTHENTICATION_FAILED']
    ]);
}

// --- Account Endpoints ---
if ($uri === '/api/v1/account/balance' && $method === 'GET') {
    jsonResponse(200, [
        'success' => true,
        'message' => 'Account balance retrieved successfully.',
        'data' => [
            'currency' => 'USD',
            'balance' => 1250.75,
            'minimum_threshold' => 100.00,
            'is_low_balance' => false,
            'auto_top_up_enabled' => true,
            'auto_top_up_amount' => 200.00,
            'auto_top_up_card_saved' => true,
            'auto_top_up_paused_reason' => null
        ]
    ]);
}

if ($uri === '/api/v1/account/threshold' && $method === 'PUT') {
    jsonResponse(200, [
        'success' => true,
        'message' => 'Threshold settings updated successfully.',
        'data' => [
            'currency' => 'USD',
            'balance' => 1250.75,
            'minimum_threshold' => (float)($jsonBody['minimum_threshold'] ?? 100),
            'auto_top_up_enabled' => !empty($jsonBody['auto_top_up_enabled']),
            'auto_top_up_amount' => (float)($jsonBody['auto_top_up_amount'] ?? 200),
            'auto_top_up_card_saved' => true
        ]
    ]);
}

if ($uri === '/api/v1/account/transactions' && $method === 'GET') {
    jsonResponse(200, [
        'success' => true,
        'message' => 'Wallet transactions retrieved successfully.',
        'data' => [
            [
                'id' => '00000000-0000-0000-0000-000000000001',
                'amount' => 12.99,
                'type' => 'Debit',
                'description' => 'Domain registration: example.com',
                'balance_after' => 1237.76,
                'created_at_utc' => '2026-09-29T12:00:00Z'
            ]
        ]
    ]);
}

if ($uri === '/api/v1/account/pricing' && $method === 'GET') {
    jsonResponse(200, [
        'success' => true,
        'message' => 'TLD pricing matrix retrieved successfully.',
        'data' => [
            [
                'tld' => '.com',
                'currency' => 'USD',
                'register_price' => 10.99,
                'renew_price' => 11.99,
                'transfer_price' => 10.99,
                'redemption_price' => 80.00,
                'grace_period_days' => 30,
                'redemption_period_days' => 30,
                'min_years' => 1,
                'max_years' => 10,
                'whois_privacy_supported' => true,
                'dnssec_supported' => true,
                'epp_code_required_for_transfer' => true
            ],
            [
                'tld' => '.net',
                'currency' => 'USD',
                'register_price' => 12.99,
                'renew_price' => 13.99,
                'transfer_price' => 12.99,
                'redemption_price' => 80.00,
                'grace_period_days' => 30,
                'redemption_period_days' => 30,
                'min_years' => 1,
                'max_years' => 10,
                'whois_privacy_supported' => true,
                'dnssec_supported' => true,
                'epp_code_required_for_transfer' => true
            ],
            [
                'tld' => '.pk',
                'currency' => 'USD',
                'register_price' => 24.99,
                'renew_price' => 24.99,
                'transfer_price' => 24.99,
                'redemption_price' => 100.00,
                'grace_period_days' => 30,
                'redemption_period_days' => 30,
                'min_years' => 2,
                'max_years' => 10,
                'whois_privacy_supported' => true,
                'dnssec_supported' => true,
                'epp_code_required_for_transfer' => true
            ]
        ]
    ]);
}

// --- Domain Availability ---
if ($uri === '/api/v1/domains/check' && $method === 'GET') {
    $domain = strtolower(trim((string)($query['domain'] ?? '')));
    if (strpos($domain, 'unsupported') !== false) {
        jsonResponse(400, [
            'success' => false,
            'message' => "The TLD is not supported.",
            'error' => ['code' => 'TLD_NOT_SUPPORTED', 'details' => []]
        ]);
    }
    $isAvail = (strpos($domain, 'avail') !== false || $domain === 'newdomain.com');
    jsonResponse(200, [
        'success' => true,
        'message' => 'Domain check completed.',
        'data' => [
            'domain' => $domain,
            'tld' => substr($domain, (int)strrpos($domain, '.')),
            'is_available' => $isAvail,
            'is_premium' => false,
            'currency' => 'USD',
            'register_price' => 10.99,
            'renew_price' => 11.99,
            'transfer_price' => 10.99,
            'redemption_price' => 80.00,
            'min_years' => 1,
            'max_years' => 10
        ]
    ]);
}

if ($uri === '/api/v1/domains/bulk-check' && $method === 'POST') {
    $domains = $jsonBody['domains'] ?? [];
    $results = [];
    foreach ($domains as $d) {
        $results[] = [
            'domain' => $d,
            'tld' => substr($d, (int)strrpos($d, '.')),
            'is_available' => (strpos($d, 'avail') !== false),
            'is_premium' => false,
            'currency' => 'USD',
            'register_price' => 10.99,
            'renew_price' => 11.99,
            'transfer_price' => 10.99,
            'redemption_price' => 80.00,
            'min_years' => 1,
            'max_years' => 10
        ];
    }
    jsonResponse(200, [
        'success' => true,
        'message' => 'Bulk domain check completed.',
        'data' => ['results' => $results]
    ]);
}

if ($uri === '/api/v1/domains/suggest' && $method === 'GET') {
    $keyword = $query['keyword'] ?? 'test';
    jsonResponse(200, [
        'success' => true,
        'data' => [
            ['domain' => $keyword . '.com', 'source' => 'Heuristic'],
            ['domain' => $keyword . '.net', 'source' => 'Heuristic']
        ]
    ]);
}

// --- Domain Registration, Renewal, Transfer ---
if ($uri === '/api/v1/domains/register' && $method === 'POST') {
    $domain = strtolower(trim((string)($jsonBody['domain'] ?? '')));
    if ($domain === '') {
        jsonResponse(400, [
            'success' => false,
            'message' => 'The request failed validation.',
            'error' => ['code' => 'VALIDATION_FAILED', 'details' => ['domain: The Domain field is required.']]
        ]);
    }
    if (strpos($domain, 'insufficient-funds') !== false) {
        jsonResponse(402, [
            'success' => false,
            'message' => 'Insufficient wallet balance.',
            'error' => ['code' => 'INSUFFICIENT_FUNDS', 'details' => []]
        ]);
    }
    $years = (int)($jsonBody['years'] ?? 1);
    // If privacy or dns management requested, mark them active
    if (!empty($jsonBody['privacy_enabled'])) {
        $state['addons'][$domain]['WhoisPrivacy'] = true;
        $state['privacy'][$domain] = true;
    }
    if (!empty($jsonBody['dns_management_enabled'])) {
        $state['addons'][$domain]['DnsManagement'] = true;
    }
    saveState($state, $stateFile);

    jsonResponse(201, [
        'success' => true,
        'message' => 'Domain registered successfully.',
        'data' => [
            'domain' => $domain,
            'order_id' => 'ORD-' . strtoupper(bin2hex(random_bytes(4))),
            'status' => 'Active',
            'registration_date_utc' => gmdate('Y-m-d\TH:i:s\Z'),
            'expiration_date_utc' => gmdate('Y-m-d\TH:i:s\Z', strtotime("+{$years} year")),
            'charged_amount' => 10.99 * $years,
            'wallet_balance_remaining' => 1200.00,
            'currency' => 'USD'
        ]
    ]);
}

if ($uri === '/api/v1/domains/renew' && $method === 'POST') {
    $domain = strtolower(trim((string)($jsonBody['domain'] ?? '')));
    $years = (int)($jsonBody['years'] ?? 1);
    jsonResponse(200, [
        'success' => true,
        'message' => 'Domain renewed successfully.',
        'data' => [
            'domain' => $domain,
            'years_added' => $years,
            'new_expiration_date_utc' => gmdate('Y-m-d\TH:i:s\Z', strtotime("+{$years} year")),
            'charged_amount' => 11.99 * $years,
            'wallet_balance_remaining' => 1188.01,
            'addons_renewed' => []
        ]
    ]);
}

if ($uri === '/api/v1/domains/transfer' && $method === 'POST') {
    $domain = strtolower(trim((string)($jsonBody['domain'] ?? '')));
    $authCode = $jsonBody['auth_code'] ?? '';
    if ($authCode === '') {
        jsonResponse(400, [
            'success' => false,
            'message' => 'The request failed validation.',
            'error' => ['code' => 'VALIDATION_FAILED', 'details' => ['auth_code: The AuthCode field is required.']]
        ]);
    }
    jsonResponse(200, [
        'success' => true,
        'message' => 'Domain transfer initiated successfully.',
        'data' => [
            'domain' => $domain,
            'transfer_id' => 'TR-' . strtoupper(bin2hex(random_bytes(4))),
            'status' => 'Pending',
            'initiated_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
            'charged_amount' => 10.99
        ]
    ]);
}

if ($uri === '/api/v1/domains/transfer/status' && $method === 'GET') {
    $domain = strtolower(trim((string)($query['domain'] ?? '')));
    if ($domain === 'completed-transfer.com') {
        jsonResponse(200, [
            'success' => true,
            'data' => [
                'domain' => $domain,
                'status' => 'Completed',
                'initiated_at_utc' => '2026-09-01T10:00:00Z',
                'completed_at_utc' => '2026-09-05T10:00:00Z'
            ]
        ]);
    } elseif ($domain === 'rejected-transfer.com') {
        jsonResponse(200, [
            'success' => true,
            'data' => [
                'domain' => $domain,
                'status' => 'Rejected',
                'reason' => 'Transfer authorization rejected by registry.',
                'initiated_at_utc' => '2026-09-01T10:00:00Z'
            ]
        ]);
    } else {
        jsonResponse(200, [
            'success' => true,
            'data' => [
                'domain' => $domain,
                'status' => 'Pending',
                'initiated_at_utc' => '2026-09-25T10:00:00Z'
            ]
        ]);
    }
}

// Portfolio list
if ($uri === '/api/v1/domains' && $method === 'GET') {
    jsonResponse(200, [
        'success' => true,
        'data' => [
            [
                'domain' => 'example.com',
                'status' => 'Active',
                'is_transfer_locked' => true,
                'is_privacy_enabled' => false,
                'auto_renew' => true,
                'expiration_date_utc' => '2027-09-29T10:00:00Z'
            ]
        ]
    ]);
}

// --- Per-domain regex routes ---
if (preg_match('#^/api/v1/domains/([^/]+)/lock$#', $uri, $m) && $method === 'PUT') {
    $domain = rawurldecode($m[1]);
    $isLocked = !empty($jsonBody['is_transfer_locked']);
    $state['locks'][$domain] = $isLocked;
    saveState($state, $stateFile);
    jsonResponse(200, [
        'success' => true,
        'message' => "Transfer lock " . ($isLocked ? "enabled" : "disabled") . " successfully.",
        'data' => ['domain' => $domain, 'is_transfer_locked' => $isLocked]
    ]);
}

if (preg_match('#^/api/v1/domains/([^/]+)/privacy$#', $uri, $m) && $method === 'PUT') {
    $domain = rawurldecode($m[1]);
    $enable = !empty($jsonBody['is_privacy_enabled']);
    // Check if WhoisPrivacy addon is active
    if ($enable && empty($state['addons'][$domain]['WhoisPrivacy'])) {
        jsonResponse(403, [
            'success' => false,
            'message' => 'WHOIS privacy protection is not active for this domain. Purchase it with POST /api/v1/domains/' . $domain . '/addons/WhoisPrivacy.',
            'error' => ['code' => 'ADDON_NOT_ACTIVE', 'details' => []]
        ]);
    }
    $state['privacy'][$domain] = $enable;
    saveState($state, $stateFile);
    jsonResponse(200, [
        'success' => true,
        'message' => "WHOIS privacy protection " . ($enable ? "enabled" : "disabled") . " successfully.",
        'data' => ['domain' => $domain, 'is_privacy_enabled' => $enable]
    ]);
}

if (preg_match('#^/api/v1/domains/([^/]+)/auto-renew$#', $uri, $m) && $method === 'PUT') {
    $domain = rawurldecode($m[1]);
    $autoRenew = !empty($jsonBody['auto_renew']);
    jsonResponse(200, [
        'success' => true,
        'message' => 'Domain auto-renew setting updated.',
        'data' => ['domain' => $domain, 'auto_renew' => $autoRenew]
    ]);
}

if (preg_match('#^/api/v1/domains/([^/]+)/auth-code$#', $uri, $m) && $method === 'GET') {
    $domain = rawurldecode($m[1]);
    jsonResponse(200, [
        'success' => true,
        'message' => 'Auth code retrieved.',
        'data' => ['domain' => $domain, 'auth_code' => 'EPP-Secret-123456']
    ]);
}

// Addons list
if (preg_match('#^/api/v1/domains/([^/]+)/addons$#', $uri, $m) && $method === 'GET') {
    $domain = rawurldecode($m[1]);
    $wpActive = !empty($state['addons'][$domain]['WhoisPrivacy']);
    $dnsActive = !empty($state['addons'][$domain]['DnsManagement']);
    jsonResponse(200, [
        'success' => true,
        'message' => 'Domain add-on services retrieved successfully.',
        'data' => [
            [
                'service_code' => 'WhoisPrivacy',
                'service_name' => 'WHOIS Privacy Protection',
                'is_offered' => true,
                'is_active' => $wpActive,
                'is_expired' => false,
                'expires_at_utc' => $wpActive ? '2027-09-29T10:00:00Z' : null,
                'is_disabled_by_admin' => false,
                'auto_renew' => true,
                'price_per_year' => 5.00,
                'default_purchase_years' => 1
            ],
            [
                'service_code' => 'DnsManagement',
                'service_name' => 'DNS Zone & NameServer Management',
                'is_offered' => true,
                'is_active' => $dnsActive,
                'is_expired' => false,
                'expires_at_utc' => $dnsActive ? '2027-09-29T10:00:00Z' : null,
                'is_disabled_by_admin' => false,
                'auto_renew' => true,
                'price_per_year' => 3.00,
                'default_purchase_years' => 1
            ]
        ]
    ]);
}

// Addon purchase
if (preg_match('#^/api/v1/domains/([^/]+)/addons/([^/]+)$#', $uri, $m) && $method === 'POST') {
    $domain = rawurldecode($m[1]);
    $serviceCode = rawurldecode($m[2]);
    $years = (int)($jsonBody['years'] ?? 1);
    $state['addons'][$domain][$serviceCode] = true;
    saveState($state, $stateFile);
    jsonResponse(200, [
        'success' => true,
        'message' => "Add-on '{$serviceCode}' purchased successfully.",
        'data' => [
            'domain' => $domain,
            'service_code' => $serviceCode,
            'years' => $years,
            'expires_at_utc' => gmdate('Y-m-d\TH:i:s\Z', strtotime("+{$years} year")),
            'charged_amount' => ($serviceCode === 'WhoisPrivacy' ? 5.00 : 3.00) * $years,
            'wallet_balance_remaining' => 1195.00,
            'currency' => 'USD'
        ]
    ]);
}

// Addon auto-renew
if (preg_match('#^/api/v1/domains/([^/]+)/addons/([^/]+)/auto-renew$#', $uri, $m) && $method === 'PUT') {
    $domain = rawurldecode($m[1]);
    $serviceCode = rawurldecode($m[2]);
    $autoRenew = !empty($jsonBody['auto_renew']);
    jsonResponse(200, [
        'success' => true,
        'message' => "Add-on auto-renew updated.",
        'data' => [
            'domain' => $domain,
            'service_code' => $serviceCode,
            'auto_renew' => $autoRenew
        ]
    ]);
}

// Domain Details
if (preg_match('#^/api/v1/domains/([^/]+)$#', $uri, $m) && $method === 'GET') {
    $domain = rawurldecode($m[1]);
    $isLocked = isset($state['locks'][$domain]) ? $state['locks'][$domain] : ($domain === 'locked.com');
    $isPrivate = isset($state['privacy'][$domain]) ? $state['privacy'][$domain] : ($domain === 'private.com');
    jsonResponse(200, [
        'success' => true,
        'message' => 'Domain retrieved successfully.',
        'data' => [
            'domain' => $domain,
            'tld' => substr($domain, (int)strrpos($domain, '.')),
            'status' => 'Active',
            'epp_statuses' => $isLocked ? ['clientTransferProhibited'] : ['ok'],
            'is_transfer_locked' => $isLocked,
            'is_registrar_locked' => $isLocked,
            'is_registry_locked' => false,
            'is_privacy_enabled' => $isPrivate,
            'auto_renew' => true,
            'created_at_utc' => '2025-09-29T10:00:00Z',
            'expiration_date_utc' => '2027-09-29T10:00:00Z',
            'days_until_expiration' => 365,
            'nameservers' => ['ns1.paknic.com', 'ns2.paknic.com'],
            'dnssec_enabled' => false,
            'contacts' => [
                'registrant' => [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'organization' => 'Paknic Corp',
                    'address1' => '123 Main St',
                    'address2' => '',
                    'city' => 'New York',
                    'state' => 'NY',
                    'postal_code' => '10001',
                    'country_code' => 'US',
                    'email' => 'john@example.com',
                    'phone' => '+1.5551234567'
                ]
            ]
        ]
    ]);
}

// --- Contacts ---
if (preg_match('#^/api/v1/contacts/([^/]+)$#', $uri, $m)) {
    $domain = rawurldecode($m[1]);
    if ($method === 'GET') {
        jsonResponse(200, [
            'success' => true,
            'message' => 'WHOIS contacts retrieved successfully.',
            'data' => [
                'registrant' => [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'organization' => 'Paknic Corp',
                    'address1' => '123 Main St',
                    'address2' => '',
                    'city' => 'New York',
                    'state' => 'NY',
                    'postal_code' => '10001',
                    'country_code' => 'US',
                    'email' => 'john@example.com',
                    'phone' => '+1.5551234567'
                ],
                'admin' => [
                    'first_name' => 'Jane',
                    'last_name' => 'Admin',
                    'organization' => 'Paknic Corp',
                    'address1' => '123 Main St',
                    'city' => 'New York',
                    'state' => 'NY',
                    'postal_code' => '10001',
                    'country_code' => 'US',
                    'email' => 'admin@example.com',
                    'phone' => '+1.5551234568'
                ],
                'tech' => null,
                'billing' => null
            ]
        ]);
    }
    if ($method === 'PUT') {
        jsonResponse(200, [
            'success' => true,
            'message' => 'WHOIS contact information updated successfully.',
            'data' => [
                'registrant' => $jsonBody['registrant'] ?? null,
                'admin' => $jsonBody['admin'] ?? null,
                'tech' => $jsonBody['tech'] ?? null,
                'billing' => $jsonBody['billing'] ?? null
            ]
        ]);
    }
}

// --- Nameservers ---
if (preg_match('#^/api/v1/nameservers/([^/]+)$#', $uri, $m)) {
    $domain = rawurldecode($m[1]);
    if ($method === 'GET') {
        jsonResponse(200, [
            'success' => true,
            'data' => [
                'domain' => $domain,
                'nameservers' => ['ns1.paknic.com', 'ns2.paknic.com']
            ]
        ]);
    }
    if ($method === 'PUT') {
        jsonResponse(200, [
            'success' => true,
            'message' => 'Nameservers updated successfully.',
            'data' => [
                'domain' => $domain,
                'nameservers' => $jsonBody['nameservers'] ?? []
            ]
        ]);
    }
}

// Child Hosts
if (preg_match('#^/api/v1/nameservers/([^/]+)/hosts$#', $uri, $m)) {
    $domain = rawurldecode($m[1]);
    if ($method === 'GET') {
        jsonResponse(200, [
            'success' => true,
            'data' => [
                ['host' => 'ns1.' . $domain, 'ipv4_addresses' => ['192.0.2.1'], 'ipv6_addresses' => []]
            ]
        ]);
    }
    if ($method === 'POST') {
        jsonResponse(201, [
            'success' => true,
            'data' => [
                'host' => $jsonBody['host'] ?? '',
                'ipv4_addresses' => $jsonBody['ipv4_addresses'] ?? [],
                'ipv6_addresses' => $jsonBody['ipv6_addresses'] ?? []
            ]
        ]);
    }
}

if (preg_match('#^/api/v1/nameservers/([^/]+)/hosts/([^/]+)$#', $uri, $m) && $method === 'DELETE') {
    $domain = rawurldecode($m[1]);
    $host = rawurldecode($m[2]);
    jsonResponse(200, ['success' => true, 'data' => ['domain' => $domain, 'host' => $host]]);
}

// --- DNS Zone ---
if (preg_match('#^/api/v1/dns/([^/]+)$#', $uri, $m)) {
    $domain = rawurldecode($m[1]);
    if ($method === 'GET') {
        $records = $state['dns'][$domain] ?? [
            ['name' => '@', 'type' => 'A', 'content' => '192.0.2.1', 'ttl' => 3600],
            ['name' => 'mail', 'type' => 'MX', 'content' => 'mail.' . $domain, 'priority' => 10, 'ttl' => 3600],
            ['name' => '_sip._tcp', 'type' => 'SRV', 'content' => 'sip.' . $domain, 'priority' => 10, 'ttl' => 3600]
        ];
        jsonResponse(200, [
            'success' => true,
            'data' => ['domain' => $domain, 'records' => $records]
        ]);
    }
    if ($method === 'POST') {
        // Must have DnsManagement addon!
        if (empty($state['addons'][$domain]['DnsManagement'])) {
            jsonResponse(403, [
                'success' => false,
                'message' => 'DNS Zone & NameServer Management is not active for this domain. Purchase it with POST /api/v1/domains/' . $domain . '/addons/DnsManagement.',
                'error' => ['code' => 'ADDON_NOT_ACTIVE', 'details' => []]
            ]);
        }
        $state['dns'][$domain] = $jsonBody['records'] ?? [];
        saveState($state, $stateFile);
        jsonResponse(200, [
            'success' => true,
            'message' => 'DNS zone records updated successfully.',
            'data' => ['domain' => $domain, 'records' => $state['dns'][$domain]]
        ]);
    }
}

// DNSSEC
if (preg_match('#^/api/v1/dns/([^/]+)/dnssec$#', $uri, $m)) {
    $domain = rawurldecode($m[1]);
    if ($method === 'GET') {
        jsonResponse(200, [
            'success' => true,
            'data' => [
                ['key_tag' => 12345, 'algorithm' => 13, 'digest_type' => 2, 'digest' => '49FD46E6C4B45C55D4AC']
            ]
        ]);
    }
    if ($method === 'POST') {
        jsonResponse(201, [
            'success' => true,
            'data' => [
                'key_tag' => (int)($jsonBody['key_tag'] ?? 0),
                'algorithm' => (int)($jsonBody['algorithm'] ?? 0),
                'digest_type' => (int)($jsonBody['digest_type'] ?? 0),
                'digest' => $jsonBody['digest'] ?? ''
            ]
        ]);
    }
}

if (preg_match('#^/api/v1/dns/([^/]+)/dnssec/(\d+)$#', $uri, $m) && $method === 'DELETE') {
    $domain = rawurldecode($m[1]);
    $keyTag = (int)$m[2];
    jsonResponse(200, ['success' => true, 'data' => ['domain' => $domain, 'key_tag' => $keyTag]]);
}

// --- Webhooks ---
if ($uri === '/api/v1/webhooks/subscriptions' && $method === 'GET') {
    jsonResponse(200, [
        'success' => true,
        'data' => [
            ['id' => '00000000-0000-0000-0000-000000000001', 'target_url' => 'https://example.com/wh', 'is_active' => true]
        ]
    ]);
}

if ($uri === '/api/v1/webhooks/subscriptions' && $method === 'POST') {
    jsonResponse(201, [
        'success' => true,
        'data' => [
            'id' => '00000000-0000-0000-0000-000000000002',
            'target_url' => $jsonBody['target_url'] ?? '',
            'description' => $jsonBody['description'] ?? '',
            'is_active' => true
        ]
    ]);
}

if (preg_match('#^/api/v1/webhooks/subscriptions/([^/]+)$#', $uri, $m) && $method === 'DELETE') {
    jsonResponse(200, ['success' => true, 'data' => ['id' => $m[1]]]);
}

// Fallback 404
jsonResponse(404, [
    'success' => false,
    'message' => "Endpoint not found: {$method} {$uri}",
    'error' => ['code' => 'NOT_FOUND']
]);

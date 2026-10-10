<?php
/**
 * WHMCS Hooks for Paknic Module
 *
 * Adds the admin dashboard wallet balance widget with a low balance warning.
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// Hook registers the admin dashboard wallet balance widget.
// All dependency loading is deferred inside getData() to avoid fatal errors during module activation.
add_hook('AdminHomeWidgets', 1, function () {
    return new class extends \WHMCS\Module\AbstractWidget {
        protected $title = 'Paknic Wholesale Wallet Balance';
        protected $description = 'Displays your live wholesale account balance on Paknic.';
        protected $weight = 15;
        protected $columns = 1;
        protected $cache = true;
        protected $cacheExpiry = 300;

        public function getData()
        {
            try {
                // Autoload SDK safely
                if (file_exists(__DIR__ . '/lib/paknic-sdk/autoload.php')) {
                    require_once __DIR__ . '/lib/paknic-sdk/autoload.php';
                } elseif (file_exists(__DIR__ . '/vendor/autoload.php')) {
                    require_once __DIR__ . '/vendor/autoload.php';
                }

                // Ensure WHMCS registrar functions are available
                if (!function_exists('getRegistrarConfigOptions')) {
                    $whmcsRoot = defined('ROOTDIR') ? ROOTDIR : dirname(__DIR__, 3);
                    if (file_exists($whmcsRoot . '/includes/registrarfunctions.php')) {
                        require_once $whmcsRoot . '/includes/registrarfunctions.php';
                    }
                }

                if (!function_exists('getRegistrarConfigOptions') || !class_exists('\\DomainRegistrar\\SDK\\Client')) {
                    return ['not_configured' => true];
                }

                // Decrypted settings; tblregistrars stores them encrypted.
                $settings = getRegistrarConfigOptions('paknic');

                if (empty($settings['ApiKey']) || empty($settings['ApiSecret'])) {
                    return ['not_configured' => true];
                }

                $client = new \DomainRegistrar\SDK\Client(new \DomainRegistrar\SDK\Configuration(
                    $settings['ApiKey'],
                    $settings['ApiSecret'],
                    !empty($settings['ApiUrl']) ? $settings['ApiUrl'] : \DomainRegistrar\SDK\Configuration::DEFAULT_BASE_URL
                ));
                return $client->account()->getBalance();
            } catch (\Exception $e) {
                return ['error' => $e->getMessage()];
            }
        }

        public function generateOutput($data)
        {
            if (isset($data['error'])) {
                return "<div class='alert alert-danger mb-0 small'>Error: " . htmlspecialchars($data['error'], ENT_QUOTES) . "</div>";
            }
            if (!empty($data['not_configured'])) {
                return "<div class='text-muted small'>Please configure Paknic API credentials in Domain Registrars.</div>";
            }

            $bal = number_format((float)($data['balance'] ?? 0), 2);
            $min = number_format((float)($data['minimum_threshold'] ?? 0), 2);
            $isLow = !empty($data['is_low_balance']);

            $badgeClass = $isLow ? "badge-danger" : "badge-success";

            return "<div class='p-3 text-center'>
                <div class='text-muted small'>Paknic Wholesale Wallet Balance</div>
                <div class='h3 font-weight-bold my-2 text-primary'>\${$bal}</div>
                <div><span class='badge {$badgeClass}'>" . ($isLow ? "Low Balance Warning (Threshold: \${$min})" : "Healthy") . "</span></div>
            </div>";
        }
    };
});

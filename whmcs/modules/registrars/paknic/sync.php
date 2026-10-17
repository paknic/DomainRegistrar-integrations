<?php
/**
 * TLD Pricing & Margin Sync Cron for WHMCS (Paknic Module)
 *
 * Reads the prices this API key is charged (GET /api/v1/account/pricing), adds your markup, and writes
 * register / renew / transfer prices into WHMCS domain pricing for every currency.
 *
 * Only TLDs already set up in WHMCS (Configuration > Domain Pricing) are updated, and by default only those
 * whose automatic registrar is Paknic. TLDs missing from WHMCS are listed, never created.
 *
 * TLDs that are winding down at Paknic (GET /api/v1/tlds/lifecycle: sales paused, sunsetting, migrating, retired)
 * have the prices that are closed set to -1 (not offered): registration and transfer once registration closes,
 * renewal after the renewal cut-off. Each is reported with its dates and written to the WHMCS activity log.
 *
 * Usage: php -q sync.php [--markup=15%|--markup=2.50] [--round=.99] [--all] [--dry-run]
 *   --markup   percent ("15%") or fixed amount ("2.50") added to each price; default none
 *   --round    make prices end in this fraction, rounding up (e.g. .99); default: nearest cent
 *   --all      also update TLDs whose automatic registrar is not Paknic
 *   --dry-run  print what would change, write nothing
 */

if (PHP_SAPI !== 'cli') {
    die("sync.php runs from the command line only.\n");
}

require_once __DIR__ . '/../../../init.php';
require_once ROOTDIR . '/includes/registrarfunctions.php';
require_once __DIR__ . '/paknic.php';
require_once __DIR__ . '/lib/PriceSync.php';

use Paknic\Whmcs\PriceSync;
use WHMCS\Database\Capsule;

$options = getopt('', ['markup::', 'round::', 'all', 'dry-run']);
$dryRun = isset($options['dry-run']);
$allTlds = isset($options['all']);

try {
    $markup = PriceSync::parseMarkup($options['markup'] ?? '');
    $ending = PriceSync::parseRounding($options['round'] ?? '');
} catch (InvalidArgumentException $e) {
    fwrite(STDERR, "[Paknic TLD Sync] " . $e->getMessage() . "\n");
    exit(2);
}

// getRegistrarConfigOptions decrypts the stored settings; tblregistrars holds them encrypted.
$registrarSettings = getRegistrarConfigOptions('paknic');
if (empty($registrarSettings['ApiKey']) || empty($registrarSettings['ApiSecret'])) {
    fwrite(STDERR, "[Paknic TLD Sync] Paknic API credentials are not configured in WHMCS.\n");
    exit(1);
}

$currencies = Capsule::table('tblcurrencies')->get(['id', 'code', 'rate'])->all();
$usd = null;
foreach ($currencies as $currency) {
    if (strtoupper($currency->code) === 'USD') {
        $usd = $currency;
    }
}
if ($usd === null) {
    fwrite(STDERR, "[Paknic TLD Sync] API prices are in USD. Add a USD currency with its exchange rate in WHMCS first.\n");
    exit(1);
}

echo "[Paknic TLD Sync] Fetching the pricing matrix from the API...\n";
try {
    $pricingList = paknic_getClient($registrarSettings)->account()->getPricing();
} catch (Exception $e) {
    fwrite(STDERR, "[Paknic TLD Sync] Error: " . $e->getMessage() . "\n");
    exit(1);
}
echo "[Paknic TLD Sync] " . count($pricingList) . " TLDs from the API. Markup: "
    . ($markup[0] === 'percent' ? $markup[1] . '%' : number_format($markup[1], 2))
    . ", rounding: " . ($ending === null ? 'nearest cent' : 'ends in ' . number_format($ending, 2))
    . ($dryRun ? " (dry run)" : "") . "\n";

$updated = 0;
$skipped = [];
foreach ($pricingList as $tld) {
    $extension = strtolower($tld['tld']);
    $domainPricing = Capsule::table('tbldomainpricing')->where('extension', $extension)->first();
    if ($domainPricing === null) {
        $skipped[] = "{$extension} (not set up in WHMCS)";
        continue;
    }
    if (!$allTlds && strtolower((string)$domainPricing->autoreg) !== 'paknic') {
        $skipped[] = "{$extension} (automatic registrar is '" . ($domainPricing->autoreg ?: 'none') . "'; use --all)";
        continue;
    }

    foreach (['domainregister', 'domainrenew', 'domaintransfer'] as $type) {
        $usdColumns = PriceSync::columnsFor($tld, $type, $markup, $ending);
        foreach ($currencies as $currency) {
            $columns = PriceSync::convert($usdColumns, (float)$usd->rate, (float)$currency->rate, ['fixed', 0.0], $ending);
            if ($dryRun) {
                echo sprintf("  %-10s %-15s %s  1yr=%s\n", $extension, $type, $currency->code, number_format($columns['msetupfee'], 2));
                continue;
            }
            $key = ['type' => $type, 'currency' => $currency->id, 'relid' => $domainPricing->id];
            if (Capsule::table('tblpricing')->where($key)->exists()) {
                Capsule::table('tblpricing')->where($key)->update($columns);
            } else {
                Capsule::table('tblpricing')->insert($key + $columns);
            }
        }
    }
    $updated++;
    echo " - {$extension}: register \${$tld['register_price']}, renew \${$tld['renew_price']}, transfer \${$tld['transfer_price']} (cost/yr)\n";
}

foreach ($skipped as $line) {
    echo "   skipped {$line}\n";
}
echo "[Paknic TLD Sync] " . ($dryRun ? "Would update" : "Updated") . " {$updated} TLD(s); skipped " . count($skipped) . ".\n";

// TLDs winding down (GET /api/v1/tlds/lifecycle): they are not in the pricing matrix, so stop selling what is closed
// here too. Registration and transfer close when registration closes; renewal closes at the renewal cut-off. Prices
// still open (usually renewal of a sunsetting TLD) keep the value last synced.
echo "[Paknic TLD Sync] Checking TLDs that are winding down...\n";
try {
    $lifecycle = paknic_getClient($registrarSettings)->tlds()->lifecycle();
} catch (Exception $e) {
    $lifecycle = null;
    fwrite(STDERR, "[Paknic TLD Sync] Could not read TLD lifecycle (prices above were still updated): " . $e->getMessage() . "\n");
}

$closedCount = 0;
foreach ($lifecycle ?? [] as $entry) {
    $extension = strtolower((string)($entry['tld'] ?? ''));
    $domainPricing = $extension === '' ? null : Capsule::table('tbldomainpricing')->where('extension', $extension)->first();
    if ($domainPricing === null) {
        continue; // not sold in this WHMCS
    }
    if (!$allTlds && strtolower((string)$domainPricing->autoreg) !== 'paknic') {
        continue;
    }

    $rule = PriceSync::lifecycleRule($entry);
    echo " ! {$extension}: {$rule['note']}\n";
    if ($rule['close'] === [] || $dryRun) {
        continue;
    }
    foreach ($rule['close'] as $type) {
        foreach ($currencies as $currency) {
            $key = ['type' => $type, 'currency' => $currency->id, 'relid' => $domainPricing->id];
            if (Capsule::table('tblpricing')->where($key)->exists()) {
                Capsule::table('tblpricing')->where($key)->update(PriceSync::notOfferedColumns());
            } else {
                Capsule::table('tblpricing')->insert($key + PriceSync::notOfferedColumns());
            }
        }
    }
    $closedCount++;
    if (function_exists('logActivity')) {
        logActivity("Paknic TLD Sync: {$extension} {$rule['note']}");
    }
}
if ($lifecycle !== null) {
    echo "[Paknic TLD Sync] " . count($lifecycle) . " TLD(s) winding down at Paknic; "
        . ($dryRun ? "would close" : "closed") . " prices on {$closedCount} here.\n";
}

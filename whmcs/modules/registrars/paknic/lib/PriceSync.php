<?php
/**
 * Price calculation for sync.php, kept free of WHMCS calls so it can be tested on its own.
 */

namespace Paknic\Whmcs;

final class PriceSync
{
    /** tblpricing columns holding the price for 1..10 years of a domain term. */
    public const YEAR_COLUMNS = [
        1 => 'msetupfee', 2 => 'qsetupfee', 3 => 'ssetupfee', 4 => 'asetupfee', 5 => 'bsetupfee',
        6 => 'monthly', 7 => 'quarterly', 8 => 'semiannually', 9 => 'annually', 10 => 'biennially',
    ];

    /** WHMCS treats -1 as "this term is not offered". */
    public const NOT_OFFERED = -1.0;

    /**
     * Parses --markup: "15%" adds 15 percent, "2.50" adds a fixed amount, empty adds nothing.
     *
     * @return array{0: string, 1: float} ['percent'|'fixed', value]
     */
    public static function parseMarkup(?string $markup): array
    {
        $markup = trim((string)$markup);
        if ($markup === '') {
            return ['fixed', 0.0];
        }
        if (substr($markup, -1) === '%') {
            $value = substr($markup, 0, -1);
            if (!is_numeric($value)) {
                throw new \InvalidArgumentException("Invalid --markup value '{$markup}'.");
            }
            return ['percent', (float)$value];
        }
        if (!is_numeric($markup)) {
            throw new \InvalidArgumentException("Invalid --markup value '{$markup}'.");
        }
        return ['fixed', (float)$markup];
    }

    /**
     * Parses --round: ".99" (or "0.99") makes every price end in .99, rounding up; empty rounds to cents.
     */
    public static function parseRounding(?string $round): ?float
    {
        $round = trim((string)$round);
        if ($round === '') {
            return null;
        }
        if (!is_numeric($round) || (float)$round < 0 || (float)$round >= 1) {
            throw new \InvalidArgumentException("Invalid --round value '{$round}': use a fraction such as .99 or .00.");
        }
        return (float)$round;
    }

    /**
     * Cost plus markup, then rounded up to the chosen ending (never below cost plus markup).
     */
    public static function sellPrice(float $cost, array $markup, ?float $ending): float
    {
        [$kind, $value] = $markup;
        $price = $kind === 'percent' ? $cost * (1 + $value / 100) : $cost + $value;
        $price = round($price, 2);
        if ($ending === null) {
            return $price;
        }
        $candidate = floor($price) + $ending;
        if ($candidate + 0.000001 < $price) {
            $candidate += 1;
        }
        return round($candidate, 2);
    }

    /**
     * The tblpricing column values for one TLD and price type, in the API's currency (USD).
     *
     * @param string $type domainregister | domainrenew | domaintransfer
     * @return array<string, float> column => price
     */
    public static function columnsFor(array $tld, string $type, array $markup, ?float $ending): array
    {
        $minYears = max(1, (int)($tld['min_years'] ?? 1));
        $maxYears = min(10, (int)($tld['max_years'] ?? 10));
        $perYear = (float)($type === 'domainregister' ? $tld['register_price']
            : ($type === 'domainrenew' ? $tld['renew_price'] : $tld['transfer_price']));

        $columns = [];
        foreach (self::YEAR_COLUMNS as $years => $column) {
            // A transfer adds one year; the API has no multi-year transfer.
            $offered = $type === 'domaintransfer' ? $years === 1 : ($years >= $minYears && $years <= $maxYears);
            $columns[$column] = $offered ? self::sellPrice($perYear * $years, $markup, $ending) : self::NOT_OFFERED;
        }
        return $columns;
    }

    /**
     * What a TLD from GET /api/v1/tlds/lifecycle means for WHMCS pricing: the price types to stop offering
     * (set to -1 for every term) and a line for the sync report. Registration and transfer stop together when
     * registration closes; renewal stops when renewals close.
     *
     * @return array{close: string[], note: string}
     */
    public static function lifecycleRule(array $entry): array
    {
        $close = [];
        if (empty($entry['registration_open'])) {
            $close[] = 'domainregister';
            $close[] = 'domaintransfer';
        }
        if (empty($entry['renewals_open'])) {
            $close[] = 'domainrenew';
        }

        $status = (string)($entry['status'] ?? 'unknown');
        $date = static function ($value): ?string {
            if (empty($value)) {
                return null;
            }
            $ts = strtotime((string)$value);
            return $ts === false ? null : gmdate('Y-m-d', $ts);
        };
        $parts = [$status];
        if ($d = $date($entry['no_renewals_after'] ?? null)) {
            $parts[] = "last renewal {$d}";
        }
        if ($d = $date($entry['retire_at'] ?? null)) {
            $parts[] = "retires {$d}";
        }
        $note = implode(', ', $parts) . '; '
            . ($close === [] ? 'nothing closed' : 'not offered: ' . implode(', ', array_map(
                static fn ($t) => substr($t, strlen('domain')), $close)));
        return ['close' => $close, 'note' => $note];
    }

    /** Every term of a price type marked not offered. */
    public static function notOfferedColumns(): array
    {
        return array_fill_keys(array_values(self::YEAR_COLUMNS), self::NOT_OFFERED);
    }

    /**
     * Converts USD column values into a WHMCS currency. WHMCS rates are relative to the default currency, so
     * USD → target is (value / usdRate) * targetRate. Rounding is re-applied after conversion.
     */
    public static function convert(array $columns, float $usdRate, float $targetRate, array $markupNone, ?float $ending): array
    {
        if ($usdRate <= 0) {
            throw new \InvalidArgumentException('The USD currency rate in WHMCS must be above zero.');
        }
        $out = [];
        foreach ($columns as $column => $value) {
            $out[$column] = $value === self::NOT_OFFERED
                ? self::NOT_OFFERED
                : self::sellPrice($value / $usdRate * $targetRate, $markupNone, $ending);
        }
        return $out;
    }
}

<?php

declare(strict_types=1);

namespace WHMCS\Domains\DomainLookup {
    if (!defined('WHMCS')) {
        define('WHMCS', true);
    }
    if (!class_exists('WHMCS\Domains\DomainLookup\SearchResult')) {
        class SearchResult {
            public const STATUS_NOT_REGISTERED = 'available';
            public const STATUS_REGISTERED = 'unavailable';
            public const STATUS_TLD_NOT_SUPPORTED = 'tld_not_supported';
            public const STATUS_UNKNOWN = 'unknown';

            private string $searchTerm;
            private string $tld;
            private string $status = '';

            public function __construct(string $searchTerm, string $tld) {
                $this->searchTerm = $searchTerm;
                $this->tld = $tld;
            }
            public function setStatus(string $status): void {
                $this->status = $status;
            }
            public function getStatus(): string {
                return $this->status;
            }
        }
    }

    if (!class_exists('WHMCS\Domains\DomainLookup\ResultsList')) {
        class ResultsList implements \IteratorAggregate {
            private array $results = [];
            public function append(SearchResult $r): void {
                $this->results[] = $r;
            }
            public function getIterator(): \Traversable {
                return new \ArrayIterator($this->results);
            }
            public function count(): int {
                return count($this->results);
            }
            public function offsetGet(int $i): SearchResult {
                return $this->results[$i];
            }
        }
    }
}

namespace WHMCS\Domain\TopLevel {
    if (!class_exists('WHMCS\Domain\TopLevel\ImportItem')) {
        class ImportItem {
            public string $extension = '';
            public int $minYears = 1;
            public int $maxYears = 10;
            public float $registerPrice = 0.0;
            public float $renewPrice = 0.0;
            public float $transferPrice = 0.0;
            public int $redemptionFeeDays = 30;
            public float $redemptionFeePrice = 0.0;
            public string $currency = 'USD';
            public bool $eppRequired = true;

            public function setExtension(string $v): self { $this->extension = $v; return $this; }
            public function setMinYears(int $v): self { $this->minYears = $v; return $this; }
            public function setMaxYears(int $v): self { $this->maxYears = $v; return $this; }
            public function setRegisterPrice(float $v): self { $this->registerPrice = $v; return $this; }
            public function setRenewPrice(float $v): self { $this->renewPrice = $v; return $this; }
            public function setTransferPrice(float $v): self { $this->transferPrice = $v; return $this; }
            public function setRedemptionFeeDays(int $v): self { $this->redemptionFeeDays = $v; return $this; }
            public function setRedemptionFeePrice(float $v): self { $this->redemptionFeePrice = $v; return $this; }
            public function setCurrency(string $v): self { $this->currency = $v; return $this; }
            public function setEppRequired(bool $v): self { $this->eppRequired = $v; return $this; }
        }
    }
}

namespace WHMCS\Results {
    if (!class_exists('WHMCS\Results\ResultsList')) {
        class ResultsList implements \IteratorAggregate {
            private array $items = [];
            public function append(\WHMCS\Domain\TopLevel\ImportItem $item): void {
                $this->items[] = $item;
            }
            public function getIterator(): \Traversable {
                return new \ArrayIterator($this->items);
            }
            public function count(): int {
                return count($this->items);
            }
        }
    }
}

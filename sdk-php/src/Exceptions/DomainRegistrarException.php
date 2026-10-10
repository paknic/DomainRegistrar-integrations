<?php

declare(strict_types=1);

namespace DomainRegistrar\SDK\Exceptions;

use Exception;

class DomainRegistrarException extends Exception
{
    private ?string $errorCode;
    private array $details;

    public function __construct(
        string $message,
        int $statusCode = 0,
        ?string $errorCode = null,
        array $details = [],
        ?Exception $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
        $this->errorCode = $errorCode;
        $this->details = $details;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getDetails(): array
    {
        return $this->details;
    }
}

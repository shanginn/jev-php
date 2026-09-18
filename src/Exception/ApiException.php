<?php

declare(strict_types=1);

namespace Shanginn\Jev\Exception;

class ApiException extends JevException
{
    public function __construct(public readonly int $status, public readonly ?string $requestId = null, public readonly ?float $retryAfter = null)
    {
        // Do not expose upstream bodies: they can echo private state or credentials.
        parent::__construct('OpenRouter Decisions request failed (HTTP ' . $status . ').', $status);
    }
}

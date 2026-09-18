<?php

declare(strict_types=1);

namespace Shanginn\Jev\Http;

final readonly class RetryPolicy
{
    /** @param list<int> $statuses */
    public function __construct(public int $maxRetries = 2, public float $initialDelay = 0.5, public float $maxDelay = 10.0, public array $statuses = [408, 429, 500, 502, 503, 504, 524, 529])
    {
        if ($maxRetries < 0 || $maxRetries > 10 || !is_finite($initialDelay) || !is_finite($maxDelay) || $initialDelay < 0 || $maxDelay < $initialDelay) {
            throw new \InvalidArgumentException('Retries must be 0–10 and delays finite, non-negative and ordered.');
        }
    }

    public function delay(int $status, int $retry, ?float $retryAfter): ?float
    {
        if ($retry >= $this->maxRetries || !in_array($status, $this->statuses, true)) {
            return null;
        }
        // Never retry sooner than Retry-After. If too long, return control to the caller.
        if ($retryAfter !== null) {
            return $retryAfter <= $this->maxDelay ? max(0.0, $retryAfter) : null;
        }
        return min($this->maxDelay, $this->initialDelay * (2 ** $retry)) * (random_int(500, 1000) / 1000);
    }
}

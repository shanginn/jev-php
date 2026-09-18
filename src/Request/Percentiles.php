<?php

declare(strict_types=1);

namespace Shanginn\Jev\Request;

final readonly class Percentiles implements \JsonSerializable
{
    public function __construct(public ?float $p50 = null, public ?float $p75 = null, public ?float $p90 = null, public ?float $p99 = null)
    {
        foreach (get_object_vars($this) as $value) {
            if ($value !== null && (!is_finite($value) || $value < 0)) {
                throw new \InvalidArgumentException('Percentile thresholds must be finite and non-negative.');
            }
        }
    }

    public function jsonSerialize(): object
    {
        return (object) array_filter(get_object_vars($this), static fn(?float $v): bool => $v !== null);
    }
}

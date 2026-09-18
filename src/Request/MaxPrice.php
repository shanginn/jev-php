<?php

declare(strict_types=1);

namespace Shanginn\Jev\Request;

final readonly class MaxPrice implements \JsonSerializable
{
    public function __construct(public ?string $prompt = null, public ?string $completion = null, public ?string $request = null, public ?string $image = null, public ?string $audio = null)
    {
        foreach (get_object_vars($this) as $price) {
            if ($price !== null && (!is_numeric($price) || !is_finite((float) $price) || (float) $price < 0)) {
                throw new \InvalidArgumentException('Prices must be non-negative decimal strings.');
            }
        }
    }

    public function jsonSerialize(): object
    {
        return (object) array_filter(get_object_vars($this), static fn(?string $v): bool => $v !== null);
    }
}

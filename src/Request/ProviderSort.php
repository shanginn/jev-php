<?php

declare(strict_types=1);

namespace Shanginn\Jev\Request;

final readonly class ProviderSort implements \JsonSerializable
{
    public function __construct(public ?string $by = null, public ?string $partition = null) {}

    public function jsonSerialize(): object
    {
        return (object) array_filter(['by' => $this->by, 'partition' => $this->partition], static fn(?string $v): bool => $v !== null);
    }
}

<?php

declare(strict_types=1);

namespace Shanginn\Jev\Response;

final readonly class Usage
{
    public function __construct(public int $inputTokens, public int $outputTokens, public ?float $cost = null) {}

    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }
}

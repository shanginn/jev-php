<?php

declare(strict_types=1);

namespace Shanginn\Jev\Response;

/** @template T of object */
final readonly class TypedResponse
{
    /** @param T $value */
    public function __construct(public object $value, public DecisionResponse $response) {}
}

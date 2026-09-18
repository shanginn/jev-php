<?php

declare(strict_types=1);

namespace Shanginn\Jev\Request;

final readonly class Trace implements \JsonSerializable
{
    /** @param array<string, mixed> $metadata Additional JSON-serializable trace metadata. */
    public function __construct(public ?string $traceId = null, public ?string $traceName = null, public ?string $spanName = null, public ?string $generationName = null, public ?string $parentSpanId = null, public array $metadata = [])
    {
        foreach (['trace_id', 'trace_name', 'span_name', 'generation_name', 'parent_span_id'] as $key) {
            if (array_key_exists($key, $metadata)) {
                throw new \InvalidArgumentException('Use the named constructor argument for known trace fields.');
            }
        }
    }

    public function jsonSerialize(): object
    {
        return (object) (array_filter(['trace_id' => $this->traceId, 'trace_name' => $this->traceName, 'span_name' => $this->spanName, 'generation_name' => $this->generationName, 'parent_span_id' => $this->parentSpanId], static fn(?string $v): bool => $v !== null) + $this->metadata);
    }
}

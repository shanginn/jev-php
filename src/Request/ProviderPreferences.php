<?php

declare(strict_types=1);

namespace Shanginn\Jev\Request;

/** OpenRouter's complete provider routing surface; string values remain forward-compatible. */
final readonly class ProviderPreferences implements \JsonSerializable
{
    /**
     * @param list<string>|null $order
     * @param list<string>|null $only
     * @param list<string>|null $ignore
     * @param list<string>|null $quantizations
     */
    public function __construct(
        public ?array $order = null,
        public ?bool $allowFallbacks = null,
        public ?bool $requireParameters = null,
        public ?string $dataCollection = null,
        public ?bool $zdr = null,
        public ?array $only = null,
        public ?array $ignore = null,
        public ?array $quantizations = null,
        public string|ProviderSort|null $sort = null,
        public ?MaxPrice $maxPrice = null,
        public float|Percentiles|null $preferredMinThroughput = null,
        public float|Percentiles|null $preferredMaxLatency = null,
        public ?bool $enforceDistillableText = null,
    ) {
        if ($dataCollection !== null && !in_array($dataCollection, ['allow', 'deny'], true)) {
            throw new \InvalidArgumentException('dataCollection must be allow or deny.');
        }
        foreach ([$order, $only, $ignore, $quantizations] as $values) {
            if ($values === null) {
                continue;
            }
            if (!array_is_list($values)) {
                throw new \InvalidArgumentException('Provider lists must be lists of strings.');
            }
            foreach ($values as $value) {
                if (!is_string($value) || trim($value) === '') {
                    throw new \InvalidArgumentException('Provider values must be non-empty strings.');
                }
            }
        }
        foreach ([$preferredMinThroughput, $preferredMaxLatency] as $value) {
            if (is_float($value) && (!is_finite($value) || $value < 0)) {
                throw new \InvalidArgumentException('Routing thresholds must be finite and non-negative.');
            }
        }
    }

    public function jsonSerialize(): object
    {
        return (object) array_filter([
            'order' => $this->order, 'allow_fallbacks' => $this->allowFallbacks,
            'require_parameters' => $this->requireParameters, 'data_collection' => $this->dataCollection,
            'zdr' => $this->zdr, 'only' => $this->only, 'ignore' => $this->ignore,
            'quantizations' => $this->quantizations, 'sort' => $this->sort, 'max_price' => $this->maxPrice,
            'preferred_min_throughput' => $this->preferredMinThroughput,
            'preferred_max_latency' => $this->preferredMaxLatency,
            'enforce_distillable_text' => $this->enforceDistillableText,
        ], static fn(mixed $v): bool => $v !== null);
    }
}

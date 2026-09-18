<?php

declare(strict_types=1);

namespace Shanginn\Jev\Http;

final readonly class HttpResponse
{
    /** @param array<string, list<string>> $headers */
    public function __construct(public int $status, public string $body, public array $headers = []) {}

    public function header(string $name): ?string
    {
        foreach ($this->headers as $key => $values) {
            if (strcasecmp($key, $name) === 0) {
                return $values[0] ?? null;
            }
        }
        return null;
    }
}

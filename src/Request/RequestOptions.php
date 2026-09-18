<?php

declare(strict_types=1);

namespace Shanginn\Jev\Request;

final readonly class RequestOptions implements \JsonSerializable
{
    public function __construct(
        public ?ProviderPreferences $provider = null,
        public ?string $sessionId = null,
        public ?string $user = null,
        public ?Trace $trace = null,
    ) {
        foreach ([$sessionId, $user] as $value) {
            if ($value !== null && preg_match('/^.{0,256}$/usD', $value) !== 1) {
                throw new \InvalidArgumentException('Session and user identifiers must be valid UTF-8, at most 256 characters.');
            }
        }
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return array_filter(['provider' => $this->provider, 'session_id' => $this->sessionId, 'user' => $this->user, 'trace' => $this->trace], static fn(mixed $v): bool => $v !== null);
    }
}

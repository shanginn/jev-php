<?php

declare(strict_types=1);

namespace Shanginn\Jev\Tests;

use Amp\Cancellation;
use Shanginn\Jev\Http\{HttpResponse, Transport};

final class FixtureTransport implements Transport
{
    /** @var list<string> */
    public array $requests = [];

    /** @param list<HttpResponse|\Throwable> $responses */
    public function __construct(private array $responses) {}

    public function send(string $json, ?Cancellation $cancellation = null): HttpResponse
    {
        $this->requests[] = $json;
        $response = array_shift($this->responses) ?? throw new \LogicException('Fixture exhausted.');
        if ($response instanceof \Throwable) {
            throw $response;
        }
        return $response;
    }

    /** @param array<string|int, array<string, mixed>> $answers */
    public static function response(array $answers): HttpResponse
    {
        return new HttpResponse(200, json_encode([
            'model' => 'typesafe/jev-1.13', 'answers' => (object) $answers,
            'usage' => ['input_tokens' => 120, 'output_tokens' => 0, 'cost' => 0.00000504],
            'id' => 'gen-fixture', 'provider' => 'TypeSafe',
        ], JSON_THROW_ON_ERROR), ['X-Request-ID' => ['req-fixture']]);
    }
}
